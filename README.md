Gestionnaire de groupes GROUPIE
===============================

<h1> Description de Groupie </h1>
Groupie est un logiciel de gestion de groupes.

Il se compose d'une interface web développée sous Symfony 4.2 et de plusieurs scripts effectuant des opérations sur le LDAP.


Groupie permet de gérer plusieurs types de groupes :

- Groupes institutionnels
Ce sont les groupes créés par l'administrateur de Groupie. La gestion des membres s'effectue soit :
    - par un ou plusieurs administrateurs qui peuvent ajouter/supprimer des membres ou administrateurs. Par exemple, un groupe pour les administrateurs d'une application.
    - par alimentation automatique à partir d'un filtre LDAP. Par exemple, un groupe pour les membres d'un service.
    - par alimentation automatique à partir d'une table d'une base de données. Par exemple, un groupe pour les utilisateurs d'une application (exemple Apogée).

L'utilisateur de Groupie peut visualiser les groupes dont il est membre.

Si l'utilisateur est administrateur de Groupie, il peut visualiser les groupes qu'il gère et accéder aux fonctions d'ajout/suppression de membres.

Si l'utilisateur a un rôle DOSI, il peut visualiser tous les groupes.

- Groupes de partage, qui peuvent être créés par des utilisateurs identifiés, avec un nommage précis correspondant à une partie de l'arborescence.
Les utilisateurs identifiés ont un rôle de créateur, qui leur permet de créer, modifier ou supprimer des groupes depuis une arborescence donnée.
Les créateurs peuvent créer des groupes avec ou sans filtre (paramétrable avec creatorfilter: true)  

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
    - amuGroupCreator : dn du ou des utilisateurs qui peuvent créer des groupes à partir de cette arborescence 
    - amuGroupofGroup : booléen pour indiquer si on fait un filtre sur l'ou groups
  Le nom des attributs est paramétrable.
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
Appartenance au groupe LDAP : "univ:glob:personnel"
- ROLE_GESTIONNAIRE : l'utilisateur est administrateur d'un ou de groupes. Il a accès en visualisation aux groupes dont il fait partie, et il peut modifier les membres des groupes qu'il gère.
Appartenance au groupe LDAP : "univ:app:groupie:gest"
- ROLE_CREATEUR : l'utilisateur peut créer des groupes. Il est positionné en 'amuGroupCreator' sur un groupe. Il peut effectuer toutes les opérations sur les groupes descendant de groupe parent.
  Appartenance au groupe LDAP : "univ:app:groupie:creat"  
- ROLE_DOSI : l'utilisateur est membre de la DOSI, il accède en visualisation à toutes les infos des groupes.
Appartenance au groupe LDAP : "univ:svc:dosi"
- ROLE_PRIVE : l'utilisateur peut accéder à la partie "groupes privés".
Appartenance au groupe LDAP : "univ:svc:dosi"
- ROLE_ADMIN : l'utilisateur a tous les droits sur tous les groupes, ainsi que les droits de création/modification/suppression de groupes.
Appartenance au groupe LDAP : "univ:app:groupie:admin"
- ROLE_SUPER_ADMIN : partie développeur

NB: Les groupes sont paramétrables

Les vues
--------------------------------------------------------------------------
On dispose de 5 onglets et de plusieurs sous-menus :

* Administrer mes groupes 
* Voir mes appartenances
* Voir tous les groupes  
* Recherche :
    * Rechercher un groupe
    * Rechercher une personne
* Groupes privés 
* Administrer Groupie
    * Créer un groupe
    * Modifier un groupe  
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
git clone https://github.com/EsupPortail/esup-groupie.git
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
                creator: amuGroupCreator
                creatorfilter: true
                owner: owner
                groupofgroup: amuGroupOfGroup
                separator: ':'
                max_name_size: 63
                name_regex: "/([^a-z0-9:])/"
                phrase_regex: "Le nom ne doit pas excéder 63 caractères. Les caractères autorisés pour le nommage sont les lettres minuscules, les chiffres et le séparateur :"
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
    - { name: ROLE_CREATEUR,       type: ldap, rule: '(&(memberof=cn=creat,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_CREATEUR_FILTRE,type: ldap, rule: '(&(memberof=cn=filtcreat,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_DOSI,           type: ldap, rule: '(&(memberof=cn=dosi,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_PRIVE,          type: ldap, rule: '(&(memberof=cn=dosi,ou=groups,dc=univ,dc=fr)(uid=login))' }
    - { name: ROLE_ADMIN,          type: ldap, rule: '(&(memberof=cn=groupie,ou=groups,dc=univ,dc=fr)(uid=login))' }
</pre>


Scripts PERL LDAP pour peupler des groupes basés sur des filtres (plus efficace que dynlist)
--------------------------------------------------------------------------------------------
Chez nous les scripts et autres définitions sont sous /var/ldap dans les répertoires etc cron et lib
Ils doivent s'exécuter sur un LDAP Maitre (lecture du slapd.conf de OpenLDAP et du password root (en clair))
Il conviendra de remplacer par vos attributs maison si besoin et de changer les chemins si besoin.

* Dans etc:
	fichier hosts contient des définitions
* Dans lib:
	utils2.pm librairie pour lire /etc/openldap/slapd.conf (rootdn rootpw suffix..)
* Dans cron (modifier les quelques variables si besoin)  
	SyncAllGroups.pl synchronise les membres des groupes qui ont un attribut contenant un filtre de type LDAP ou SQL  
	exemples de filtres dans l'attribut amuGroupfilter:  
	* SQL: dbi:mysql:host=apogee.univ.fr;port=3306;database=fwa2|user|pass|SELECT * from V_USERS_APOGEE  
	* LDAP: (&(amudatevalidation=*)(amuComposante=odontologie)(eduPersonAffiliation=faculty)) 

	SyncGroupSID.pl synchronise la branche Active Directory vers le LDAP pour ajouter l'attribut objectsid. Permet de renommer dans LDAP et de renommer dans l'AD.  
	SyncADGroups.pl synchronise la branche ou=groups LDAP avec une branche ou=groups Active Directory  

Schéma LDAP
----------------------------------------------------------------------------------
Le nom des attributs est paramétrable.

        objectclass   ( 1.3.6.1.4.1.7135.1.1.2.2.7 NAME 'AMUGroup' SUP top AUXILIARY
            DESC 'Groupes spécifiques AMU '
            MAY ( amuGroupFilter $ amuGroupCreator $ amuGroupAdmin $ amuGroupMember $ amuGroupOfGroup $ objectSid $ supannEntiteAffectation))

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

        attributetype ( 1.3.6.1.4.1.7135.1.3.131.3.46 NAME 'amuGroupOfGroup'
            DESC 'Groupe de Groupe '
            EQUALITY booleanMatch
            SINGLE-VALUE
            SYNTAX 1.3.6.1.4.1.1466.115.121.1.7 )

        attributetype (  1.3.6.1.4.1.7135.1.3.131.3.44 NAME 'amuGroupCreator'
          DESC 'RFC2256: createur de groupes'
          SUP distinguishedName )


L'attribut amuGroupMember permet d'ajouter manuellement une personne à un groupe qui est alimenté par un filtre. Cet attribut ne se modifie pas dans l'interface.
L'attribut amuGroupOfGroup permet de peupler un groupe avec un filtre définissant un groupe de groupes. Si l'attribut est positionné à TRUE, le filtre se fait dans l'ou groups.
Il faut également penser à positionner les droits sur ces attributs pour l'utilisateur privilégié que vous utilisez (acl.conf).
