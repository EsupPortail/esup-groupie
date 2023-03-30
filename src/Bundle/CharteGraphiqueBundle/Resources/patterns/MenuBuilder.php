<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class MenuBuilder extends Container
{
    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var
     */
    private $authorizationChecker;

    /**
     * MenuBuilder constructor.
     * @param FactoryInterface $factory
     * @param AuthorizationChecker $authorizationChecker
     */
    public function __construct(FactoryInterface $factory, AuthorizationChecker $authorizationChecker)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function createSideBarMenu(RequestStack $requestStack)
    {
        $menu = $this->factory->createItem('root');

//    ************
//    * Exemples *
//    ************
//
//        Ajout d'un item simple
//        ----------------------
//
        $menu->addChild('Accueil',['route' => 'homepage',])
            ->setExtras(['icon'=> 'home']);  // Lien vers la bibliothèque d'icônes pour faire vos choix => https://material.io/tools/icons/?style=baseline
//
//
//        Ajout d'un item si l'utilisateur est authentifié
//        ------------------------------------------------
//
//        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
//                $menu->addChild('Accueil',['route' => 'homepage',])
//                    ->setExtras(['icon'=> 'home']);
//        }
//
//        Ajout d'un item si l'utilisateur est admin
//        ------------------------------------------
//
//        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')){
//            $menu->addChild('Administration',['route' => 'admin'])
//                ->setExtras(['icon'=> 'key']);
//        }

        return $menu;
    }
}


