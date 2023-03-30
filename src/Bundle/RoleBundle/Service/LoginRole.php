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
 * Classe qui définie un rôle en fonction d'un login ou d'un tableau de login.
 *
 * @author Arnaud Salvucci <arnaud.salvucci@univ-amu.fr>
 */

namespace App\Bundle\RoleBundle\Service;

/**
 * Class LoginRole.
 */
class LoginRole
{
    /**
     * LoginRole constructor.
     *
     * @param $login
     * @param $rule
     */
    public function __construct($login, $rule)
    {
        $this->login = $login;
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

        if (in_array($this->login, $this->rule['rule'])) {
            $role = $this->rule['name'];
        }

        return $role;
    }
}
