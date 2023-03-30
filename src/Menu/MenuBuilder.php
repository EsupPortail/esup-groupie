<?php
/*
 * Copyright 2022, ESUP-Portail  http://www.esup-portail.org/
 *  Licensed under APACHE2
 *  @author  Peggy FERNANDEZ BLANCO <peggy.fernandez-blanco@univ-amu.fr>
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */

namespace App\Menu;

use App\Entity\Roles;
use App\Repository\RolesRepository;
use Doctrine\ORM\EntityManager;
use Knp\Menu\FactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class MenuBuilder extends Container
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var TokenStorage
     */
    private $tokenStorage;

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
     * @param TokenStorage $tokenStorage
     */
    public function __construct(FactoryInterface $factory, AuthorizationChecker $authorizationChecker, TokenStorage $tokenStorage)
    {
        parent::__construct();
        $this->factory = $factory;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
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
//
//        Ajout d'un item si l'utilisateur est authentifié
//        ------------------------------------------------
//
        if( $this->authorizationChecker->isGranted('ROLE_ADMIN') ||
            $this->authorizationChecker->isGranted('ROLE_DOSI') ||
            $this->authorizationChecker->isGranted('ROLE_GESTIONNAIRE')) {
            $menu->addChild('Administrer mes groupes', ['route' => 'my_groups',])
                ->setExtras(['icon' => 'group_add']);  // Gérer les groupes dont je suis admin
        }
        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            $menu->addChild('Voir mes appartenances',['route' => 'memberships',])
                ->setExtras(['icon'=> 'person']);
        }

        if( $this->authorizationChecker->isGranted('ROLE_ADMIN') ||
            $this->authorizationChecker->isGranted('ROLE_DOSI') ) {
            $menu->addChild('Voir tous les groupes', ['route' => 'all_groups',])
                ->setExtras(['icon' => 'supervisor_account']);  // Voir tous les groupes
        }

        if( $this->authorizationChecker->isGranted('ROLE_ADMIN') ||
            $this->authorizationChecker->isGranted('ROLE_DOSI') ||
            $this->authorizationChecker->isGranted('ROLE_GESTIONNAIRE')) {
            $menu->addChild('Recherche', ['route' => 'search',])
                ->setExtras(['icon' => 'search']);  // Recherche de groupes ou personnes
        }

        if( $this->authorizationChecker->isGranted('ROLE_ADMIN') ||
            $this->authorizationChecker->isGranted('ROLE_DOSI') ) {
            $menu->addChild('Groupes privés', ['route' => 'private',])
                ->setExtras(['icon' => 'lock']);  // Groupes privés
        }
//
//        Ajout d'un item si l'utilisateur est admin
//        ------------------------------------------
//
//        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')){
//            $menu->addChild('Administration',['route' => 'admin'])
//                ->setExtras(['icon'=> 'key']);
//        }
        if( $this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $menu->addChild('Administrer Groupie', ['route' => 'gestion',])
                ->setExtras(['icon' => 'settings_applications']);  // Ajout/modification/suppression de groupes par les admin

        }

        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            $menu->addChild('Aide', ['route' => 'help',])
                ->setExtras(['icon' => 'help']);  // Lien vers la bibliothèque d'icônes pour faire vos choix => https://material.io/tools/icons/?style=baseline
        }

        return $menu;
    }
}


