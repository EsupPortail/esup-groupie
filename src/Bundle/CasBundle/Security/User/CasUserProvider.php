<?php
/*
 * Copyright 2022, ESUP-Portail  http://www.esup-portail.org/
 *  Licensed under APACHE2
 *  @author  Laure DENOIX
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */


namespace App\Bundle\CasBundle\Security\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use App\Bundle\RoleBundle\Service\Role;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Ldap\Ldap;

class CasUserProvider implements UserProviderInterface
{
    private $roleService;
    private $options;


    public function setRoleService(Role $roleService) {

        $this->roleService = $roleService;
    }

    public function setLdap(Ldap $ldapService, array $ldapParam) {

        $this->options['ldap']['service'] = $ldapService;
        $this->options['ldap']['params'] = $ldapParam;
    }


    public function setRequest(RequestStack $request) {

        $this->options['ip'] = $request->getCurrentRequest()->getClientIp();
    }

    public function loadUserByUsername($login)
    {
        $roles = array();
        return new CasUser($login, '', '',$roles, $this->roleService, $this->options);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof CasUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return CasUser::class === $class;
    }
}