Gestionnaire de groupes GROUPIE
===============================

<h1> Description de Groupie </h1>
Groupie est un logiciel de gestion de groupes.

Il se compose d'une interface web développée sous Symfony 2.7 et de plusieurs scripts effectuant des opérations sur le LDAP.


Groupie permet de gérer 2 types de groupes :

- Groupes institutionnels
Ce sont les groupes créés par l'administrateur de Groupie. La gestion des membres s'effectue soit :
    - par un ou plusieurs administrateurs qui peuvent ajouter/supprimer des membres ou administrateurs. Par exemple, un groupe pour les administrateurs d'une application.
    - par alimentation automatique à partir d'un filtre LDAP. Par exemple, un groupe pour les membres d'un service.
    - par alimentation automatique à partir d'une table d'une base de données. Par exemple, un groupe pour les utilisateurs d'une application (exemple Apogée).

L'utilisateur de Groupie peut visualiser les groupes dont il est membre.

Si l'utilisateur est administrateur de Groupie, il peut visualiser les groupes qu'il gère et accéder aux fonctions d'ajout/suppression de membres.

Si l'utilisateur a un rôle DOSI, il peut visualiser tous les groupes.

- Groupes privés
Ce sont des groupes créés et gérés par l'utilisateur. Ils sont préfixés par "amu:perso". Chaque utilisateur peut :
    - créer un ou plusieurs groupes privés, dans la limite de 20 groupes maximum par utilisateur
    - supprimer ses groupes privés
    - ajouter des membres dans ses groupes
    - supprimer des membres dans ses groupes

Au niveau LDAP
==========================================================================
- Création d'une branche ou=groups dans le LDAP
- Dans cette branche, création de ou=private pour gérer les groupes privés
- Plusieurs attributs ont été ajoutés au niveau des groupes :
    - amuGroupFilter : filtre LDAP si le groupe est alimenté automatiquement
    - amuGroupAdmin : dn du ou des administrateurs du groupe
- Scripts d'alimentation qui tournent régulièrement sont sur la machine LDAP
    - SyncAllGroups.pl : met à jour les groupes alimentés par des filtres LDAP ou par une table d'une base Oracle.
    - SyncADGroups.pl : met à jour les groupes dans l'AD.

NB: Le nommage dans le LDAP peut être changé et est paramétrable dans l'application.

Au niveau de l'interface
==========================================================================
Les rôles
--------------------------------------------------------------------------
On identifie 6 rôles dans l'application :

- ROLE_MEMBRE : C'est le rôle de base. L'utilisateur est seulement membre d'un ou de groupes. Il a seulement accès à la visualisation des groupes dont il fait partie.
Appartenance au groupe LDAP : "amu:glob:ldap:personnel"
- ROLE_GESTIONNAIRE : l'utilisateur est administrateur d'un ou de groupes. Il a accès en visualisation aux groupes dont il fait partie, et il peut modifier les membres des groupes qu'il gère.
Appartenance au groupe LDAP : "amu:app:grp:grouper:grouper-ent"
- ROLE_DOSI : l'utilisateur est membre de la DOSI, il accède en visualisation à toutes les infos des groupes.
Appartenance au groupe LDAP : "amu:svc:dosi:tous"
- ROLE_PRIVE : l'utilisateur peut accéder à la partie "groupes privés".
Appartenance au groupe LDAP : "amu:svc:dosi:tous"
- ROLE_ADMIN : l'utilisateur a tous les droits sur tous les groupes, ainsi que les droits de création/modification/suppression de groupes.
Appartenance au groupe LDAP : "amu:adm:app:groupie"
- ROLE_SUPER_ADMIN : partie développeur

NB: Les groupes sont paramétrables

Les vues
--------------------------------------------------------------------------
On dispose de 5 onglets et de plusieurs sous-menus :

* Groupes institutionnels :
    * Dont je suis membre
    * Dont je suis administrateur
    * Voir tous les groupes
* Recherche :
    * Rechercher un groupe
    * Rechercher une personne
* Groupes privés :
    * Dont je suis membre
    * Dont je suis administrateur
    * Tous les groupes privés
* Gestion des groupes
    * Créer un groupe
    * Supprimer un groupe
* Aide
    * Aide groupes institutionnels
    * Aide groupes privés


<h1>Installation Groupie v2 (Symfony 4.2)</h1>

<h2><b>Nouvelle version de Groupie</b></h2>

Cette nouvelle version inclut :
la mise à jour du framework Symfony vers la version 4
la mise à jour de la charte graphique et de l'apparence suivant le modèle préconisé par le pôle web
de nouvelles fonctionnalités comme la modification des noms et caractéristiques des groupes, ou la vérification des filtres LDAP

<h2> <b>Prérequis</b> </h2>

* Php 7.4
* composer installé
* yarn installé


<h2> <b>Installation du projet</b>       </h2>

* Récupérer le projet depuis github

<pre>
git clone https://github.com/peggyfb/groupie.git
</pre>

* Lancer :

<pre>
$ composer update
</pre>

* Récupérer la charte graphique

<li>Installer les dependances necessaires</li>

<pre>
$ yarn install
$ yarn add sass-loader@7.0.1 node-sass --dev
$ yarn add bootstrap@3 jquery material-design-icons-iconfont
$ yarn add datatables.net-bs
</pre>

<li>Puis lancer</li>

<pre>
$ yarn run encore dev
</pre>

<h2> <b>Paramétrage</b>           </h2>

* Il faut configurer le ficher .env (renommer .env.example).
Seuls les paramètres LDAP sont à renseigner (pas de base de données, pas d'envoi de mails)

Le reste du paramétrage s'effectue dans les fichiers de config dans config. Il faut renommer les fichiers "example" et adapter les paramètres.

* services.yml : La partie à configurer concerne 'parameters'

        # charte graphique
            amu_chartegraphique_nom_appli: 'Groupie -  Gestion des groupes LDAP'         # Le nom de l'application
            amu_chartegraphique_slogan: ''            # Le slogan de l'application
            amu_chartegraphique_auteur: ''            # Le nom de l'auteur de l'application
            amu_chartegraphique_contact_referent: ''  # L'adresse mail du référent fonctionnel
            amu_chartegraphique_titre_onglet: ''      # Le titre qui sera affiché dans l'onglet

            # configuration LDAP
            ldap_param:
                rel_dn: '%env(relative_dn)%'
                pwd: '%env(ldappassword)%'
                base_dn: '%env(base_dn)%'

            # configuration groupie
            logs:
                facility: LOG_SYSLOG
            users:
                people_branch: ou=people
                login: uid
                name: sn
                givenname: givenName
                displayname: cn
                mail: mail
                tel: telephoneNumber
                comp: amuComposante
                aff: amuAffectationlib
                primaff: eduPersonPrimaryAffiliation
                campus: amuCampus
                site: amuSite
                filter: (&(!(eduPersonPrimaryAffiliation=student))(!(eduPersonPrimaryAffiliation=alum))(amuDateValidation=*))
            groups:
                object_class:
                    - groupOfNames
                    - AMUGroup
                    - top
                group_branch: ou=groups
                cn: cn
                desc: description
                member: member
                memberof: memberOf
                groupfilter: amuGroupFilter
                groupadmin: amuGroupAdmin
                separator: ':'
            private:
                private_branch: ou=private
                prefix: amu:perso

* cas_guard.yml : paramètrage du CAS et hiérarchie des rôles

       # Enter the hostname of the CAS server.
       hostname:             cas.fr # Required, Example: example.org

        # Server cas port
        port:                 443 # Example: 443

        # REQUEST_PATH of the CAS server.
        url:                  cas # Example: cas/login

       # Version of the CAS Server.
       version:              '3.0' # One of "3.0"; "2.0"; "1.0", Example: 3.0

* roles.yml : Il faut configurer les groupes qui auront les différents droits dans l'application

<pre>
role:
  rules:
    - { name: ROLE_MEMBRE,         type: ldap, rule: '(&(memberof=cn=personnel,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_GESTIONNAIRE,   type: ldap, rule: '(&(memberof=cn=gest,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_DOSI,           type: ldap, rule: '(&(memberof=cn=dosi,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_PRIVE,          type: ldap, rule: '(&(memberof=cn=dosi,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_ADMIN,          type: ldap, rule: '(&(memberof=cn=groupie,ou=groups,dc=univ,dc=fr)(uid=login))' }
</pre>


Scripts PERL LDAP pour peupler des groupes basés sur des filtres (plus efficace que dynlist)
--------------------------------------------------------------------------------------------
Chez nous les scripts et autres définitions sont sous /var/ldap dans les répertoires etc cron et lib
Ils doivent s'exécuter sur un LDAP Maitre (lecture du slapd.conf de OpenLDAP et du password root (en clair))

* Dans etc:
	fichier hosts contient des définitions
* Dans lib:
	utils2.pm librairie pour lire /etc/openldap/slapd.conf (rootdn rootpw suffix..)
* Dans cron (modifier les quelques variables si besoin)
	SyncAllGroups.pl synchronise les membres des groupes qui ont un attribut contenant un filtre de type LDAP ou SQL
	exemples de filtres dans l'attribut amuGroupfilter:
	* SQL: dbi:mysql:host=apogee.univ.fr;port=3306;database=fwa2|user|pass|SELECT * from V_USERS_APOGEE
	* LDAP: (&(amudatevalidation=*)(amuComposante=odontologie)(eduPersonAffiliation=faculty))

	SyncADGroups.pl synchronise la branche ou=groups LDAP avec une branche ou=groups Active Directory

Schéma LDAP
----------------------------------------------------------------------------------

		objectclass   ( 1.3.6.1.4.1.7135.1.1.2.2.7 NAME 'AMUGroup' SUP top AUXILIARY
			 DESC 'Groupes spécifiques AMU '
			 MAY ( amuGroupFilter $ amuGroupAdmin $ amuGroupAD $ amuGroupMember ))

		attributetype (  1.3.6.1.4.1.7135.1.3.131.3.40 NAME 'amuGroupAdmin'
			DESC 'RFC2256: admin of a group'
			SUP distinguishedName )

		attributetype ( 1.3.6.1.4.1.7135.1.3.131.3.41 NAME 'amuGroupFilter'
			DESC 'Filtre LDAP pour les groupes'
			 EQUALITY caseIgnoreMatch
			 SUBSTR caseIgnoreSubstringsMatch
			 SYNTAX 1.3.6.1.4.1.1466.115.121.1.15{256} )

		attributetype (  1.3.6.1.4.1.7135.1.3.131.3.45 NAME 'amuGroupMember'
			DESC 'manual member of a group in a group by filter using ldapadd'
			SUP distinguishedName )

		attributetype ( 1.3.6.1.4.1.7135.1.3.131.3.42 NAME 'amuGroupAD'
           DESC 'Export AD '
           EQUALITY booleanMatch
           SINGLE-VALUE
          SYNTAX 1.3.6.1.4.1.1466.115.121.1.7 )
