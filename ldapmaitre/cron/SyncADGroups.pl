#!/usr/bin/perl
#Dominique LALOT AMU:
use strict;
use Data::Dumper;
use Net::LDAPS;
use Net::LDAP qw(LDAP_SUCCESS LDAP_PROTOCOL_ERROR);
use Net::LDAP::Control::Paged;
use Net::LDAP::Constant qw( LDAP_CONTROL_PAGED LDAP_CONTROL_TREE_DELETE);

my %maitre;
my %Hmail;
my $VERBOSE=0;
my $TEST=0;
our $masterhost;
my $admin;  # mode admin
our $rootpw;
our $rootdn;
our $suffix;
use lib '/var/ldap/lib/';
require "utils2.pm";
use Getopt::Std;
my $modif=0;
my $domain='salsa';
my $filter='*';
my %AD=();
my $cookie;
my $groupattr='amugroup';
my %HUserAD=();


our ($opt_h, $opt_v,$opt_t,$opt_u,$opt_f);
getopts('tvhuf:');
if (defined($opt_h)){
   print "Usage: [-u] [-t] [-v] [-f] groupe sans le cn=
 -v mode verbose\n  -t teste seulement -u mode incremental toutes les 10 minutes\n";
   exit;
}
$TEST=1 if (defined($opt_t));
$VERBOSE=1 if (defined($opt_v));
if (defined($opt_f)) {
    $filter=$opt_f;
    $filter=~s/cn=//i;  # au cas ou on se loupe..
}

my $page= Net::LDAP::Control::Paged->new(size => 20000) or die $!;
&GetAD();

&LitLdapConfig(); # rempli rootdn rootpass suffix
my $ldap = Net::LDAP->new($masterhost) or die "$@";
my $mesg = $ldap->bind($rootdn, password => $rootpw, version => 3 );
if ($mesg->code()) { print "Pb bind admin $!";exit -3;}

my $ldapAD = Net::LDAPS->new($AD{host}) or die "PB AD $AD{host} $@";
my $mesg = $ldapAD->bind($AD{admindn}, password => $AD{adminpass}, version => 3 );
if ($mesg->code()) { print "Pb bind admin AD $!";exit -3;}
my $filterAD=$filter;
$filterAD=~s/:/_/g;

# Lire les groupes LDAP
print "search groupes LDAP $filter\n";
$mesg = $ldap->search (  # perform a search
         base   => "ou=groups,$suffix",
         filter => "cn=$filter",
         attrs => ['cn','member','description','objectsid'],
         scope => 'one');
print "Groupes LDAP ".$mesg->count."\n" if $VERBOSE;
my @entries= $mesg->entries;
my %HGmember=();
foreach my $entr (@entries){
    %HGmember=();
    my $groupeDN=$entr->dn;
    my $cn=$entr->get_value('cn');
    my $sid=$entr->get_value('objectsid');
    my $desc=$entr->get_value('description');
    #next unless $cn eq 'amu:ufr:sciences:ldap:enseignants';
    print "LDAP: $cn" if $VERBOSE;
    my @res=$entr->get_value('member');
    my $members=0;
    foreach (@res) {
        #print "LDAP member: $_\n" if $VERBOSE;
        if (/uid=(.*),ou=people,$suffix/i){
            my $uid=$1;
            # pseudomember qu'on évite..
            next if $uid=~/groupie|gaiap|pedagiciel|geststmed|siamu|cartamu|alumforce|sesame|henneberg.s/;
            $HGmember{lc $uid}=1;
            $members++;
        }
    }
    print "\t$members\n" if $VERBOSE;
    &Group($cn,\%HGmember,$desc,$sid);
}

sub Group {
    my ($group,$HGmember,$desc,$sid)=@_;
    my %HADmember=();
    my @Tadd=();
    my @Tdelete=();
    my @Tchanges=();
    my $ADD=0;
    my $DELETE=0;
    my $groupeDN;
    $group=~s/:/_/g;
    my $index=0;
    my $members=0;
#    return unless $group eq 'amu_app_grp_exploitpatrimoine_affectunitepiecetest_users';
#http://search.cpan.org/dist/perl-ldap-0.54/lib/Net/LDAP/FAQ.pod#How_do_I_search_for_all_members_of_a_large_group_in_AD?
    #print "GroupAD: $group\n";
    while($index ne '*') {
        my $searchattr;
        ($index > 0) ? ($searchattr="member;range=$index-*") : ($searchattr='member' );
        $mesg = $ldapAD->search (  # perform a search
                 base   =>"cn=$group,ou=groups,$AD{'base'}",
                 filter => 'objectclass=*',
                 attrs  => [$searchattr],
                 scope => 'base');
        #print $mesg->count()."\n";
        if ($mesg->code == LDAP_SUCCESS) {
            my $entry = $mesg->entry(0);
            my $attr;
            # large group: let's do the range option dance
            if (($attr) = grep(/^member;range=/, $entry->attributes)) {
                  my @Tmember=$entry->get_value($attr);
                  foreach (@Tmember) {
                        if (/cn=(.*),ou=people,$AD{'base'}/i){
                            #print "\t cn:$1\n" if $VERBOSE;
                            $HADmember{lc $1}=1; # attention le member est case sensitif côté AD
                            $members++;
                        }
                  }
                  if ($attr =~ /^member;range=\d+-(.*)$/) {
                     $index = $1;
                     $index++  if ($index ne '*');
                  }
            }
            else { # small group: no need for the range dance
                  my @Tmember=$entry->get_value($searchattr);
                  foreach (@Tmember) {
            #            print "trace $_\n";
                        if (/cn=(.*),ou=people,$AD{'base'}/i){
                            #print "\t cn:$1\n";
                            $HADmember{lc $1}=1; # attention le member est case sensitif côté AD
                            $members++;
                        }
                  }
                  last;
            }
         }
         else {last;}# failed
    } #while index
    if ($mesg->code != LDAP_SUCCESS) {
        if ( $mesg->error=~/NO_OBJECT/ && $mesg->count==0 ){
                return unless AddGroup($group,$desc,$sid);
        }
        else {
            print "$group: ".$mesg->error."\n";
            return;
        }
    }
    #return;
    print "AD $group $members\n" if $VERBOSE;
    my ($todelete,$toadd)=(0,0);
    my @lignes=();
    my $i=0; # il faut détruire ou ajout par petites quantités..
    foreach (keys %{$HGmember}){
     #   print "LDAPmember $_\n";
        unless (defined $HADmember{$_}){
            #next unless defined $HUserAD{$_}; # user dans l'AD
            $i++;
            next if $i>10000;
            push @lignes, "\tajout $_\n" if $VERBOSE;
            push @Tadd,"CN=$_,ou=People,$AD{'base'}";;
            $ADD=1;
            $toadd++;
        }
    }
    foreach (keys %HADmember){
    #    print "ADmember $_\n";
      unless (defined $HGmember->{$_}){
            next if $i>10000;
            $i++;
            push @lignes, "\tdelete $_\n" if $VERBOSE;
            push @Tdelete,"CN=$_,ou=People,$AD{'base'}";
            $DELETE=1;
            $todelete++;
      }
     }
    return unless ($ADD or $DELETE);
    print "Groupe $group $members Add $toadd Del $todelete\n" if ($ADD or $DELETE);
    print @lignes if $VERBOSE;
    unless ($TEST){
       if ($DELETE){
            push @Tchanges,'delete',['member',\@Tdelete];
        }
        if ($ADD){
            push @Tchanges,'add',['member',\@Tadd];
        }
        my $groupeDN="CN=$group,OU=Groups,$AD{'base'}";
        my $result = $ldapAD->modify($groupeDN, changes => \@Tchanges);
        if ($result->code){
            print Dumper  @Tchanges;
            warn "Pb ajout/suppression de membres $groupeDN: ", $result->error."\n" ;
            print Dumper(@Tdelete);
        }
        else {print "Modifications $groupeDN\n" if (($ADD or $DELETE) and $VERBOSE);}
    }
}

sub search{
    my ($uid)=@_;
     $mesg = $ldapAD->search (  # perform a search
             base   =>$uid,
             filter => 'objectclass=*',
             attrs  => [''],
             scope => 'base');
    unless ($mesg->count()){
        print "$uid non trouvé\n" if $VERBOSE;
        return 0;
    }
    return 1;
}

sub AddGroup{
    my ($group,$desc,$sid)=@_;
    my @attrs=();

    $mesg = $ldapAD->search (  # cherche si on a renommé le groupe
        base   =>"ou=groups,$AD{'base'}",
        filter => "objectsid=$sid",
        attrs  => ['cn'],
        scope => 'one');
    if ($mesg->count()){ # on renomme le groupe trouve grace au sid
        my $entry = $mesg->entry(0);
        my $dn=$entry->dn;
        my $res = $ldapAD->moddn( $dn, newrdn => "cn=$group",deleteoldrdn => 1 );
        print "Renomme $dn $group\n" if $VERBOSE;
        $res->code && warn "Peut pas modifier le groupe $dn: ", $res->error ;
        my $groupeDN="CN=$group,OU=Groups,$AD{'base'}";
        my $result = $ldapAD->modify($groupeDN,replace => {'samaccountname'=> $group});
        return 0;
    }

    push @attrs,'objectClass',['top','group'];
    push @attrs,'cn',$group;
    push @attrs,'samaccountname',$group;
    push @attrs,'description',$desc if $desc;
    my $groupeDN="CN=$group,OU=Groups,$AD{'base'}";
    print "AJOUT $group $sid\n" if $VERBOSE;
    unless ($TEST) {
        my $result = $ldapAD->add($groupeDN,attr => \@attrs);
        $result->code && warn "Peut pas créer le groupe $groupeDN: ", $result->error ;
       print Dumper @attrs if $result->code;
        return 0;
    }
    else {
        print "Test Ajout $group\n",
    }
    return 1;
}

sub GetAD{
  open (HOSTS,'</var/ldap/etc/hosts') or die "Pas de fichier /var/ldap/etc/hosts a lire";
  while (<HOSTS>){
      chomp;
      if (/^AD(\w+):(.*)$/){
        $AD{$1}=$2;
      }
  }
  close HOSTS;
}

