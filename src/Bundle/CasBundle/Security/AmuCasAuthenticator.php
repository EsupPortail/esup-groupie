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

/**
 * This file is part of the PhpCAS Guard Bundle.
 *
 * PHP version 5.6 | 7.0 | 7.1
 *
 * (c) Alexandre Tranchant <alexandre.tranchant@gmail.com>
 *
 * @category Entity
 *
 * @author    Alexandre Tranchant <alexandre.tranchant@gmail.com>
 * @license   MIT
 *
 * @see https://github.com/Alexandre-T/casguard/blob/master/LICENSE
 */

namespace App\Bundle\CasBundle\Security;

use AlexandreT\Bundle\CasGuardBundle\Service\CasServiceInterface;
use AlexandreT\Bundle\CasGuardBundle\Security\CasAuthenticator;
use phpCAS;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Amu Cas Authenticator.
 * Surcharge du CasAuthenticator d'AlexandreT en attendant
 *
 * @category Security
 *
 * @author  Laure Denoix laure.denoix@univ-amu.fr
 * @license MIT
 */
class AmuCasAuthenticator extends \AlexandreT\Bundle\CasGuardBundle\Security\CasAuthenticator
{
    /**
     * Cas Authenticator constructor.
     *
     * @param RouterInterface     $router
     * @param CasServiceInterface $cas
     */
    public function __construct(RouterInterface $router, CasServiceInterface $cas)
    {
        $this->router = $router;
        $this->cas = $cas;
        parent::__construct($router,$cas);
    }

    /**
     * Called when authentication executed and was successful!
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the last page they visited.
     *
     * If you return null, the current request will continue, and the user
     * will be authenticated. This makes sense, for example, with an API.
     * AmuCasBundle : Ajout des attributs dans le token
     *
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $providerKey The provider (i.e. firewall) key
     *
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $token->setAttributes(phpCAS::getAttributes());
        return null;
    }

    /**
     * Logout and redirect to home page.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function onLogoutSuccess(Request $request)
    {
        phpCAS::setDebug($this->cas->getDebug());
        phpCAS::setVerbose($this->cas->isVerbose());
        phpCAS::setLang($this->cas->getLanguage());

        $uri = $this->router->generate(
            $this->cas->getRouteHomepage(),array(), UrlGeneratorInterface::ABSOLUTE_URL
        );

        phpCAS::logoutWithRedirectService($uri);
    }

    public function supports(Request $request)
    {
        return true;
    }
}
