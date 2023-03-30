La doc d'installation ici : https://projets-dev.univ-amu.fr/projects/charte-graphique/wiki/Installation_et_configuration_v3

<h1>Installation charte graphique v3 (Symfony 3.4 / Flex)</h1>
<a href="http://style-guide.univ-amu.fr" class="external">Guide CSS, documentation fournie par Jean-Baptiste Calzia du pôle Web</a></li>
<h2> <b>Prérequis</b> </h2>

Ce bundle est compatible avec <b>Symfony 3.4 Flex</b> et nécessite une version de Php minimum <b>Php 7.1</b>

<h2> <b>Installation du bundle Charte graphique</b>

* Ajouter le dans le <b>composer.json</b> (à la racine du projet) :

<pre>
#composer.json

"require": {
	//...
        "amu/charte-graphique-bundle": "3.0.x-dev", // Version en cours de test
	//...
    },
"repositories": [
        {
            "type": "git",
            "url": "ssh://gitadmin@projets-dev.univ-amu.fr/CharteGraphiqueBundle.git"
        }
    ],
</pre>

* Lancer :

<pre>
$ composer update
</pre>

Cette commande lance automatiquement :

<ul>
	<li>L'installation de <strong>symfony/security</strong> : <a href="https://symfony.com/doc/4.0/components/security.html" class="external">Doc officielle Security Component Symfony 3.4</a> / <a href="https://packagist.org/packages/symfony/security" class="external">Packagist</a> / <a href="https://github.com/symfony/security" class="external">Dépôt GitHub</a></li>
	<li>L'installation de <strong>symfony/security-bundle</strong> : <a href="https://symfony.com/doc/4.0/security.html" class="external">Doc officielle Security Symfony 3.4</a> / <a href="https://packagist.org/packages/symfony/security-bundle" class="external">Packagist</a> / <a href="https://github.com/symfony/security-bundle" class="external">Dépôt GitHub</a></li>
</ul>

<ul>
	<li>L'installation de <strong>symfony/twig-bundle</strong> : <a href="https://twig.symfony.com/doc/2.x/" class="external">Doc officielle Twig</a>  / <a href="https://symfony.com/doc/4.0/templating.html" class="external">Doc officielle Templating Symfony 3.4</a> /   <a href="https://packagist.org/packages/symfony/twig-bundle" class="external">Packagist</a> / <a href="https://github.com/symfony/twig-bundle" class="external">Dépôt GitHub</a> </li>
	<li>L'installation du <strong>knplabs/knp-menu-bundle</strong> : <a href="https://symfony.com/doc/current/bundles/KnpMenuBundle/index.html" class="external">Doc officielle Knp-Menu Symfony 3.4</a> / <a href="https://packagist.org/packages/knplabs/knp-menu-bundle" class="external">Packagist</a> / <a href="https://github.com/KnpLabs/KnpMenuBundle" class="external">Dépôt GitHub</a></li>
	<li>L'installation de <strong>symfony/webpack-encore</strong> : <a href="https://symfony.com/doc/3.4/frontend/encore/installation.htmll" class="external">Doc officielle Webpack Encore Symfony 3.4</a> / <a href="https://packagist.org/packages/symfony/webpack-encore-bundle" class="external">Packagist</a> / <a href="https://github.com/symfony/webpack-encore-bundle" class="external">Dépôt GitHub</a></li>
</ul>

<p><strong><span style="color:red;"> ATTENTION</span></strong> : si l'un ou plusieurs de ses bundles sont déjà installés dans votre projet, attention à la <a href="https://projets-dev.univ-amu.fr/projects/charte-graphique/repository/revisions/develop/entry/composer.json" class="external">version</a>.<br>Une désinstallation du bundle peut être nécessaire avant de relancer un "composer update".</p>
<h2> <b>Configuration du bundle Charte graphique</b> </h2>

<li>Pour pouvoir gérer les dépendances de Webpack Encore avec yarn (selon la <a href="https://symfony.com/doc/3.4/frontend/encore/installation.html" class="external">doc de Symfony</a>), lancer maintenant :</li>
<pre>
$ yarn install
</pre>

* Puis :

<pre>
$ yarn add sass-loader node-sass --dev
</pre>

<li>Installer ensuite <a href="https://getbootstrap.com/docs/3.3/getting-started/" class="external">Bootstrap 3</a>, <a href="https://api.jquery.com/" class="external">jQuery</a> et la <a href="https://material.io/tools/icons/?style=baseline" class="external">bibliothèque d'icônes</a> :</li>
<pre>
$ yarn add bootstrap@3 jquery material-design-icons-iconfont
</pre>

<li>Si besoin les <a href="https://datatables.net/" class="external">datatables</a> :</li> 

<pre>
$ yarn add datatables.net-bs
</pre>

<li>Creer un fichier <strong>/assets/scss/app.scss</strong> et y ajouter la ligne suivante (avant de supprimer le repertoire <strong>css/app.css</strong> et son contenu):</li>

<pre>
#assets/scss/app.scss

@import "./vendor/amu/charte-graphique-bundle/Resources/assets/scss/app.scss";
</pre>

* Modifier les lignes dans le fichier <b>app.js</b> :

<pre>
#assets/js/app.js

//...
+ var $ = require('jquery'); // Si besoin de jQuery...
- ...

+require('bootstrap/js/dropdown.js');
- ...

+ //console.log('Hello Webpack Encore! Edit me in assets/js/app.js');
- console.log('Hello Webpack Encore! Edit me in assets/js/app.js');
//...
</pre>

* Modifier les lignes dans le fichier <b>webpack.config.js</b> (à la racine du projet) :

<pre>
#webpack.config.js

//...
+ .addEntry('js/app', './assets/js/app.js')
- .addEntry('app', './assets/js/app.js')

+ //.splitEntryChunks()
- .splitEntryChunks()

+ .addStyleEntry('css/app', './assets/scss/app.scss')
- ...

+ .addEntry('images/favicon.ico', './assets/images/favicon.ico')
- ...

+ .disableSingleRuntimeChunk()
- .enableSingleRuntimeChunk()

+ .enableSassLoader()
- //.enableSassLoader()

+ .autoProvidejQuery()
- //.autoProvidejQuery()
//...

</pre>

* Puis lancer 

<pre>
$ yarn run encore dev
</pre>

* Renseigner la configuration du fichier <b>twig.yaml</b>

<pre>
#config/packages/twig.yaml

twig:
    path:
        - '%kernel.project_dir%/templates'
        - '%kernel.project_dir%/vendor/amu/charte-graphique-bundle/Resources/views/bundles/KnpMenu'
        - '%kernel.project_dir%/vendor/amu/charte-graphique-bundle/Resources/views/bundles/Bootstrap3'
        - '%kernel.project_dir%/vendor/knplabs/knp-menu/src/Knp/Menu/Resources/views'

    debug: '%kernel.debug%'
    strict_variables: '%kernel.debug%'

    form_themes: ['amu_bootstrap_base_layout.html.twig',
                  'amu_bootstrap_3_layout.html.twig',]

    globals:
        nom_appli: '%amu_chartegraphique_nom_appli%'
        slogan : '%amu_chartegraphique_slogan%'
        auteur: '%amu_chartegraphique_auteur%'
        contact: '%amu_chartegraphique_contact_referent%'
        titre_onglet: '%amu_chartegraphique_titre_onglet%'

</pre>

* Définir la valeur pour chacun des paramètres dans le fichier <b>services.yaml</b>

<pre>
#config/services.yaml

parameters:
#...
    amu_chartegraphique_nom_appli: '...'         # Le nom de l'application
    amu_chartegraphique_slogan: '...'            # Le slogan de l'application
    amu_chartegraphique_auteur: '...'            # Le nom de l'auteur de l'application
    amu_chartegraphique_contact_referent: '...'  # L'adresse mail du référent fonctionnel
    amu_chartegraphique_titre_onglet: '...'      # Le titre qui sera affiché dans l'onglet

#...
</pre>

<h3>Dernière étape : à copier-coller dans le projet</h3>

<p>Les sources des modèles se trouvent dans le répertoire : <strong>Ressources/patterns</strong> </p>

<ul>
	<li>Créer le répertoire <strong>src/Menu</strong> et y ajouter la classe <strong>MenuBuilder.php</strong> à paramétrer. Pour personnaliser les icônes du menu : <a href="https://material.io/tools/icons/?style=baseline" class="external">Lien vers la bibliothèque d'icônes</a></li>
		<li>Remplacer le <strong>contenu</strong> du template <strong>base.html.twig</strong> par celui proposé ci-dessous</li>
		<li><span style="color:red;"> ATTENTION</span> : Penser à faire un " <strong>{% extends 'base.html.twig' %}</strong> " dans vos templates...</li>
		<li>Vous pouvez maintement utiliser la <a href="http://style-guide.univ-amu.fr" class="external">documentation mise en place par Jean-Baptiste Calzia du pôle Web</a></li>
	</ul>
Enjoy !