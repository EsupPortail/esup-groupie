#!/usr/bin/perl
# D.LALOT Aout 2019
# Ajoute le SID lu dans AD au groupe LDAP pour ne pas tout casser quand on renommme un groupe
use strict;
use Data::Dumper;
use Net::LDAPS;
use Net::LDAP qw(LDAP_SUCCESS LDAP_PROTOCOL_ERROR);
use Net::LDAP::Control::Paged;
use Net::LDAP::Constant qw( LDAP_CONTROL_PAGED LDAP_CONTROL_TREE_DELETE);
use Net::LDAP::SID;

my %maitre;
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
my $domain;
my %AD=();
my $cookie;
my $groupattr='amugroup';
my %HGrpAD;


our ($opt_h, $opt_v,$opt_t,$opt_u,$opt_d);
getopts('tvhud:');
if (defined($opt_h)){
   print "Usage: [-u] [-t] [-v] [-d] salsa|lambada
 -v mode verbose\n  -t teste seulement -u mode incremental toutes les 10 minutes\n";
   exit;
}
$TEST=1 if (defined($opt_t));
$VERBOSE=1 if (defined($opt_v));
if (defined($opt_d)) {
   $domain=lc $opt_d;
}
if ($domain eq '') {
   $domain='salsa'
   #print "Usage: [-u] [-t] [-v] [-d] salsa|lambada domaine manquant!\n";
   #exit;
}

&LitLdapConfig(); # rempli rootdn rootpass suffix
my $ldap = Net::LDAP->new($masterhost) or die "$@";
my $mesg = $ldap->bind($rootdn, password => $rootpw, version => 3 );
if ($mesg->code()) { print "Pb bind admin $!";exit -3;}

my $page= Net::LDAP::Control::Paged->new(size => 20000) or die $!;
&GetAD();
my $ldapAD = Net::LDAPS->new($AD{host}) or die "PB AD $AD{host} $@";
my $mesg = $ldapAD->bind($AD{admindn}, password => $AD{adminpass}, version => 3 );
if ($mesg->code()) { print "Pb bind admin AD $!";exit -3;}

# lire les objectsid groupes AD existants
my $countAD=0;
while (1) {
      my $mesg = $ldapAD->search(base => "OU=Groups,$AD{'base'}",
                                   scope => 'sub',
                                 control => [$page],
                                  filter => '(cn=*)',
                                   attrs => ['cn','objectsid'],
      );
      die "LDAP error: server says ",$mesg->error,"\n" if $mesg->code;
      $countAD+=$mesg->count;
      foreach my $entr ($mesg->entries){
         my $dn=$entr->dn;
	 my $cn=$entr->get_value('cn');
	 $cn=~s/_/:/g;
	 my $sid=$entr->get_value('objectsid');
	 print "$cn ".GuidToString($sid)."\n" if $VERBOSE;
         $HGrpAD{lc $cn}=$sid;
      }
      # Get cookie from paged control
      my($resp)  = $mesg->control( LDAP_CONTROL_PAGED )  or last;
      $cookie    = $resp->cookie or last;

      # Set cookie in paged control
      $page->cookie($cookie);
}
print "Comptes AD: ou=groups,$AD{'base'} $countAD\n" if $VERBOSE;


#lire les groupes LDAP et poser le groupe sid
$mesg = $ldap->search (  # perform a search
		 base   => "ou=groups,$suffix",
		 #filter => 'amuGroupAD=*',
		 filter => "objectclass=$groupattr",
		 attrs => ['cn','objectsid'],
		 scope => 'one');
print "entrées ".$mesg->count."\n" if $VERBOSE;
my @entries= $mesg->entries;
my %HGmember=();
foreach my $entr (@entries){
	%HGmember=();
	my $groupeDN=$entr->dn;
	my $cn=$entr->get_value('cn');
	my $sid=$entr->get_value('objectsid');
	if (!defined $sid and defined $HGrpAD{lc $cn}){
		AddGroupSID($cn,$HGrpAD{$cn});		
	}
}

sub AddGroupSID{
	my ($cn,$sid)=@_;
	my @attrs=();
	my $groupeDN="cn=$cn,ou=groups,$suffix";
	print "AJOUT SID $cn\n" if $VERBOSE;
	unless ($TEST) {
		my $result = $ldap->modify($groupeDN,add=>{'objectsid' => $sid});
		$result->code && warn "Peut pas ajouter le sid au groupe $groupeDN: ", $result->error ;
	   	print Dumper @attrs if $result->code;
	}
	else {
		my $sidtext=GuidToString($sid);
		print "Test Ajout $sidtext $cn\n",
	}
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

sub GuidToString {
    my $stringGUID = unpack("H*", shift);
    $stringGUID =~ s/^(\w\w)(\w\w)(\w\w)(\w\w)(\w\w)(\w\w)(\w\w)(\w\w)(\w\w\w\w)/$4$3$2$1-$6$5-$8$7-$9-/;
    return $stringGUID;
}

