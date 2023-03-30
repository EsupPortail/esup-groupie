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

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Entity;


use Symfony\Component\Validator\Constraints as Assert;


class Membership {

  protected $groupname;
  protected $memberof;
  protected $adminof;
  protected $droits;
  

  /**
  * Set groupname
  *
  * @param string $groupname
 */
 public function setGroupname($groupname)
 {
    $this->groupname = $groupname;
 }
 
 /**
  * Set memberof
  *
  * @param bool $memberof
 */
 public function setMemberof($memberof)
 {
    $this->memberof = $memberof;
 }
 
 /**
  * Set adminof
  *
  * @param bool $adminof
 */
 public function setAdminof($adminof)
 {
    $this->adminof = $adminof;
 }

 public function setDroits($droits)
 {
    $this->droits = $droits;
 }
 /**
  * Get group name
  *
 */
 public function getGroupname()
 {
    return($this->groupname);
 }
 
 /**
  * Get memberof
  *
 */
 public function getMemberof()
 {
    return ($this->memberof);
 } 
 
 /**
  * Get adminof
  *
 */
 public function getAdminof()
 {
    return ($this->adminof);
 } 
 
  public function getDroits()
 {
    return ($this->droits);
 } 
}