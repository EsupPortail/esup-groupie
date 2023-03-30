<?php
/*
 * Copyright 2022, ESUP-Portail  http://www.esup-portail.org/
 *  Licensed under APACHE2
 *  @author  Arnaud Salvucci <arnaud.salvucci@univ-amu.fr>
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */

/**
 * RoleFactory : appelle la classe de traintement du role en fonction du paramètre type de la règle.
 *
 * @author Arnaud Salvucci <arnaud.salvucci@univ-amu.fr>
 */

namespace App\Bundle\RoleBundle\Service;


/**
 * Class Role.
 */
class Role
{
    /**
     * Configuration array.
     *
     * In this bundled, it is populated by configuration class and amu_roles.yaml file.
     *
     * @var array
     */
    private $configuration;

    private $options;

    /**
     * Role constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }
   /**
     * @param $login
     *
     * @return array
     */
    public function getRoles($login, $options)
    {
        $this->options = $options;
        $roleArray = array();
        foreach ($this->configuration['rules'] as $rule) {
            switch ($rule['type']) {

                case 'login':
                    $loginRole = new LoginRole($login, $rule);
                    $roleArray[] = $loginRole->getRole();
                    break;

                case 'ip':
                    $ipRole = new IpRole($this->options['ip'], $rule);
                    $roleArray[] = $ipRole->getRole();
                    break;

                case 'ldap':
                    $ldapRole = new LdapRole($login, $this->options['ldap'], $rule);
                    $roleArray[] = $ldapRole->getRole();
                    break;
            }
        }

        $roleArray = array_unique(array_filter($roleArray));

        return $roleArray;
    }
}
