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
 * Classe qui définie un rôle en fonction d'une adresse ip ou d'une plage d'adresse ip.
 *
 * @author Arnaud Salvucci <arnaud.salvucci@univ-amu.fr>
 */

namespace App\Bundle\RoleBundle\Service;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Class IpRole.
 */
class IpRole
{
    /**
     * IpRole constructor.
     *
     * @param $ip
     * @param $rule
     */
    public function __construct($ip, $rule)
    {
        $this->ip = $ip;
        $this->rule = $rule;
    }

    /**
     * Retourne le role de l'utilisateur si la règle match.
     *
     * @return string
     */
    public function getRole()
    {
        $role = '';

        if (IpUtils::checkIp($this->ip, $this->rule['rule'])) {
            $role = $this->rule['name'];
        }

        return $role;
    }
}
