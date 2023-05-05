#!/usr/bin/perl
# Fait la maj des groupes basés sur des filtres en général sur ou=people, mais aussi sur des groupes si $group2groupattr est positionné à TRUE
use strict;
use Data::Dumper;
use Net::LDAPS;
use Getopt::Std;

my %maitre;
my %Hmail;
my $VERBOSE=0;
my $TEST=0;
our $masterhost;
our $suffix;
my $admin;  # mode admin
our $rootpw;
our $rootdn;
our $suffix;
use lib '/var/ldap/lib/';
use DBI;
require "utils2.pm";
my $modif=0;
my %HldapValide;
our ($opt_h, $opt_v,$opt_t,$opt_f);
# utilisé pour les groupes SQL limités au personnel
my $filter='(&(amudatevalidation=*)(|(edupersonaffiliation=employee)(edupersonaffiliation=faculty)(edupersonaffiliation=researcher)(edupersonaffiliation=affiliate)))';
#  Sert à créer des groupes à filtre regroupant des admins
#  A changer selon votre schéma LDAP
my $adminattr='amugroupadmin';    # DN des admins de groupe
my $admincreatorattr='amugroupcreator';    # DN des admins de création de groupe
# Groupes normaux basés sur member
my $memberattr='amugroupmember';    # DN des membres groupe forcés dans un groupe à filtre (gestion manuelle en LDAP)
my $filterattr='amugroupfilter';    # nom de l'attribut contennant un filtre LDAP ou SQl 
my $group2groupattr='amugroupofgroup';    # cet attribut booléen TRUE/FALSE indique si le filtre pointe vers des groupes pour faire des groupes de groupes
my $filtergrp="$filterattr=*";
    
getopts('tvhf:d:');
if (defined($opt_h)){
   print "Usage: [-t] [-v]
   -f nom du groupe
   -v mode verbose
   -t teste seulement\n";
   exit;
}
$TEST=1 if (defined($opt_t));
$VERBOSE=1 if (defined($opt_v));
$filtergrp=($opt_f) if (defined($opt_f));

    &LitLdapConfig(); # rempli rootdn rootpass suffix
    my $ldap = Net::LDAP->new($masterhost) or die "$@";
    my $mesg = $ldap->bind($rootdn, password => $rootpw, version => 3 );
    if ($mesg->code()) { print "Pb bind admin";exit -3;}

    # Pour le personnel et les groupes SQL
    # On regarde si les comptes personnels sont toujours valides
    $mesg= $ldap->search (  # perform a search
          base   => "ou=people,$suffix",
          filter => $filter,
          attrs => ['uid'],
          scope => 'one');
   print "Personnel:  ".$mesg->count."\n" if $VERBOSE;
   my @entries= $mesg->entries;
   foreach my $entr (@entries){
      my $uid=$entr->get_value('uid');
        $HldapValide{lc $uid}=1;
   }

    # On cherche les groupes
    my @attrs=('cn',$filterattr,'member',$memberattr,$group2groupattr);
    $mesg = $ldap->search (  # perform a search
             base   => "ou=groups,$suffix",
             filter => "($filtergrp)",
             #             filter => "(&(cn=amu:ufr:smpm:ldap:etudiants)($filterattr=*))",
             attrs => \@attrs,
             scope => 'one');
    print "Groupes: ".$mesg->count."\n" if $VERBOSE;
    my @entries= $mesg->entries;
    foreach my $entr (@entries){
        my %HGmember=();
        my %HGmemberManual=();
        my $groupeDN=$entr->dn;
        my $cn=$entr->get_value('cn');
        my $filter=$entr->get_value($filterattr);
        my @res=$entr->get_value('member');
        my @manual=$entr->get_value($memberattr);
        my $group2group=$entr->get_value($group2groupattr);
        #print "groupe: $cn @attrs : $group2group\n" if $VERBOSE;
        $HGmember{lc $_}=1 foreach @res;
        $HGmemberManual{lc $_}=1 foreach @manual;
        &Group($groupeDN,$filter,\%HGmember,\%HGmemberManual,$group2group);
    }

sub Group {
    my ($groupeDN,$filter,$HGmember,$HGmemberManual,$group2group)=@_;
    my %HldapDN;
    my @Tadd=();
    my @Tdelete=();
    my @Tchanges=();
    my $ADD=0;
    my $DELETE=0;
    my $base;
    my $GROUP=0;
    my @attrs;
    my $attr='';
    #print "Sub Group filter $filter groupe2group: $group2group\n";
    # patch de M.. pour peupler grouper-ent
    unless ($filter=~/^dbi:/){ #si LDAP
        if ($group2group){
            $base="ou=groups,$suffix";
                $GROUP=1;
            if ($filter=~/$adminattr/i){ # le groupe qui contient tous les admins groupie
                $attr=$adminattr;
            }
            elsif ($filter=~/$admincreatorattr/i){ # le groupe qui contient tous les admins createurs de groupes dans groupie
                $attr=$admincreatorattr;
            }
            else {
                $attr='member'; # groupe de groupe normal dont filtre sous la forme cn=*:*
            } 
        }
        else {
            $base="ou=people,$suffix";
        }
        @attrs=($attr);
        # Gérer les filtres avec la date du jour
        if ($filter=~/#(\d+)DAYS#/){
            my $days=$1;
            my $seconds=$days*24*3600;
            my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time-$seconds);
            my $date=sprintf("%4d%02d%02d000000Z",$year+1900,$mon+1,$mday);
            $filter=~s/#\d+DAYS#/$date/;
        }
        print "$base $filter\n" if $VERBOSE;
        $mesg = $ldap->search (  # perform a search
                 base   => $base,
                 filter => $filter,
                 attrs => \@attrs,
                 scope => 'one');
        $mesg->code && warn "$groupeDN sur search $filter: ".$mesg->error."\n";
        my @entries= $mesg->entries;
        print "filter=$filter $base :".$mesg->count()."\n" if $VERBOSE and $GROUP;
        foreach my $entr (@entries){
            next if $entr->dn eq $groupeDN;
            if ($GROUP){
                my @res=$entr->get_value($attr);
                print $entr->dn."\n" if $VERBOSE;
                #print "\t $_\n" foreach(@res);
                $HldapDN{lc $_}=1 foreach (@res);
            }
            else {
                $HldapDN{lc $entr->dn}=1;
                #print $entr->dn."\n";
            }
        }
    }
    else { #SQL
        print "SQL $groupeDN $filter\n" if $VERBOSE;
        my ($dbi,$user,$pass,$sql)=split(/\|/,$filter);
        my $dbh = DBI->connect($dbi,$user,$pass) || do {warn( "$groupeDN connect ".$DBI::errstr . "\n" );return};
        my $sthSearch = $dbh->prepare( $sql );
        $sthSearch->execute();
        if ($sthSearch->err) { print "$groupeDN execute ".$DBI::errstr . "\n" ;return};
        my  $login;
        $sthSearch->bind_columns(\$login);
        while($sthSearch->fetch){
            $login = lc $login;
            unless ($HldapValide{$login}){
                print "$groupeDN $login plus actif\n" if $VERBOSE;
            }
            else {
                $HldapDN{"uid=$login,ou=people,$suffix"}=1;
            }
        }
        $sthSearch->finish();
        $dbh->disconnect();
    }
    foreach (keys %{$HGmember}){
        #print "Gmember $_\n";
        unless (defined $HldapDN{$_}){
            unless ($$HGmemberManual{$_}){
                print "$groupeDN $_ delete\n" if $VERBOSE;
              push @Tdelete,$_;
               $DELETE=1;
            }
        }
    }
    foreach (keys %HldapDN){
        #print "ldapDN $_\n";
      unless (defined $HGmember->{$_}){
            print "$groupeDN $_ ajout\n" if $VERBOSE;
         push @Tadd,$_;
           $ADD=1;
      }
     }
    foreach (keys %{$HGmemberManual}){
      #print "ldapDN $_\n";
      unless (defined $HGmember->{$_}){
         print "$groupeDN $_ ajout\n" if $VERBOSE;
         push @Tadd,$_;
         $ADD=1;
      }
   }

    unless ($TEST){
       if ($DELETE){
            push @Tchanges,'delete',['member',\@Tdelete];
        }
        if ($ADD){
            push @Tchanges,'add',['member',\@Tadd];
        }
        my $result = $ldap->modify($groupeDN, changes => \@Tchanges);
        $result->code && warn "failed to modify add $groupeDN: ", $result->error ;
        print "Modifications $groupeDN\n" if (($ADD or $DELETE) and $VERBOSE);
    }
}
