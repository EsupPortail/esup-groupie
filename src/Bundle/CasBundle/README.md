README FILE
===========

AmuCasBundle est un package permettant l'intégration du système d’authentification centralisé phpCAS de jasig dans des applications Symfony 3.4
Il s'appuie sur le Php CAS Bundle (https://github.com/Alexandre-T/casguard#php-cas-bundle), dont il surcharge certaines fonctions en attendant qu'elles soient intégrées 

* Version 2.0 : Symfony 3.4 (flex)

* Auteurs : Amu - Dosi - Pôle Developpement - Michel UBEDA SAN JOSE, Laure DENOIX


1) Installation
---------------

### Ajouter ces lignes dans votre composer.json (à la racine du projet) :

    "require": {
        ...
        "amu/cas-bundle": "^2.0",
    },
    "repositories": [
      {
        "type": "git",
        "url": "ssh://ServeurGit/CasBundle.git"
      }
    ],

### Mettre à jour *config/bundles.php*

    return [
             ...
        AlexandreT\Bundle\CasGuardBundle\CasGuardBundle::class => ['all' => true],
        Amu\Bundle\AmuCasBundle\AmuCasBundle::class => ['all' => true],
    ];

### Mettre à jour  *config/routes.yaml*


    app_cas:
        resource: Amu\Bundle\AmuCasBundle\Controller\SecurityController
        type:     annotation

### Mettre à jour *cas_guard.yaml*

    cas_guard:
        certificate: false
        debug: '%kernel.logs_dir%/%kernel.environment%.guard.log'
        hostname: ident.univ-amu.fr
        language: CAS_Languages_French
        port: 443
        url: /cas/
        route:
            homepage: index
            login: security_login
        verbose: true
        version: '3.0'
        logout:
            supported: true
            handled: true

### Mettre à jour *config/services.yaml*


    services:

        amubundle.cup_service:
            class: Amu\Bundle\AmuCasBundle\Security\User\CasUserProvider

### Mettre à jour et personnaliser *config/security.yaml*

    security:
        # https://symfony.com/doc/current/security.html#where-do-users-come-from-user-providers
        providers:
            amu_cas:
                id: amubundle.cup_service
        firewalls:
            dev:
                pattern: ^/(_(profiler|wdt)|css|images|js)/
                security: false
            secured_area:
                # this firewall applies to all URLs
                pattern: ^/
                provider: amu_cas
                guard:
                    authenticators:
                        - phpcasguard.cas_authenticator

                logout:
                    path: security_logout
                    # Route de la page de retour de déconnexion
                    target: homepage
                    success_handler: phpcasguard.cas_authenticator
                logout_on_user_change: true
                anonymous: false

        # Easy way to control access for large sections of your site
        # Note: Only the *first* access control that matches will be used
        access_control:
            - { path: ^/, roles: ROLE_CAS_AUTHENTIFIED }
