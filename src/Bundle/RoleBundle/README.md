README FILE
===========

AmuRoleBundle est un package permettant l'application de rôles dans des applications Symfony 3.4.

Il est conçu pour fonctionner avec AmuCasBundle, mais devrait être compatible avec un autre UserProvider

* Version 2.0 : Symfony 3.4 (flex)

* Auteurs : Amu - Dosi - Pôle Developpement - Michel UBEDA SAN JOSE, Laure DENOIX


1) Installation
---------------

### Ajouter ces lignes dans votre composer.json (à la racine du projet) :

    "require": {
        ...
        "amu/role-bundle": "^2.0",
    },
    "repositories": [
      {
        "type": "git",
        "url": "ssh://ServeurGit/RoleBundle.git"
      }
    ],

### Mettre à jour *config/bundles.php*

    return [
             ...
        Amu\Bundle\AmuRoleBundle\AmuRoleBundle::class => ['all' => true],
    ];

### Mettre à jour *config/services.yaml*

    # Configuration pour AmuCasBundle
    amubundle.cup_service:
        class: Amu\Bundle\AmuCasBundle\Security\User\CasUserProvider
        #Configuration pour AmuRoleBundle
        calls:
        # Si rôle par login
            - method: setRoleService
              arguments:
                  - '@amu_role'
        # Si rôle par IP
            - method: setRequest
              arguments:
                  - '@request_stack'
        # Si rôle par Ldap
            - method: setLDAP
              arguments:
                  - '@app.ldap'
                  - '%ldap_param%'

    # Config Ldap pour les rôles
    app.ldap:
        class: Symfony\Component\Ldap\Ldap
        factory:
            - 'Symfony\Component\Ldap\Ldap'
            - 'create'
        arguments:
            - 'ext_ldap'
            -
                connection_string: ldap://ldap.univ-toto.fr:123
                version: 3
                encryption: 'none'

### Renseigner *amu_roles.yaml*

    amu_role:
        rules:
            - { name: ROLE_STUDENT, type: ldap, rule: (&(uid=login)(eduPersonPrimaryAffiliation=student))}
            - { name: ROLE_PERSONNEL, type: ldap, rule: (&(uid=login)(|(eduPersonPrimaryAffiliation=employee)(eduPersonPrimaryAffiliation=faculty)(eduPersonPrimaryAffiliation=researcher)))}
            - { name: ROLE_ADMINLDAP, type: ldap, rule: '(&(memberof=cn=amu:svc:dosi:dvpt:tous,ou=groups,dc=univ-amu,dc=fr)(uid=login))'}
            - { name: ROLE_ADMINLOGIN, type: login, rule: ['login1','login2']}
            - { name: ROLE_USERIP, type: ip, rule: ['127.0.0.1','6545.564.54.1']}
