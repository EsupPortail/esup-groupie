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


class Member {

  protected $uid;
  protected $displayname;
  protected $mail;
  protected $tel;
  protected $aff;
  protected $primaff;
  protected $member;
  protected $admin;
  

  /**
  * Set uid
  *
  * @param string $uid
 */
 public function setUid($uid)
 {
    $this->uid = $uid;
 }
 
 /**
  * Set member
  *
  * @param bool $member
 */
 public function setMember($member)
 {
    $this->member = $member;
 }
 
 /**
  * Set admin
  *
  * @param bool $admin
 */
 public function setAdmin($admin)
 {
    $this->admin = $admin;
 }
 /**
  * Set displayname
  *
  * @param string displayname
 */
 public function setDisplayname($displayname)
 {
    $this->displayname = $displayname;
 }
 
 /**
  * Set mail
  *
  * @param string mail
 */
 public function setMail($mail)
 {
    $this->mail = $mail;
 }
 
 /**
  * Set tel
  *
  * @param string tel
 */
 public function setTel($tel)
 {
    $this->tel = $tel;
 }

    /**
     * Set supannEntiteAffectationPrincipale
     *
     * @param string $aff
     */
    public function setAff($aff)
    {
        $this->aff = $aff;
    }

    /**
     * Set eduPersonPrimaryAffiliation
     *
     * @param string $primaff
     */
    public function setPrimAff($primaff)
    {
        $this->primaff = $primaff;
    }

 /**
  * Get uid
  *
 */
 public function getUid()
 {
    return($this->uid);
 }
 /**
  * Get displayname
  *
 */
 public function getDisplayname()
 {
    return ($this->displayname);
 } 
 /**
  * Get mail
  *
 */
 public function getMail()
 {
    return ($this->mail);
 } 
 /**
  * Get tel
  *
 */
 public function getTel()
 {
    return ($this->tel);
 }
    /**
     * Get supannEntiteAffectation
     *
     */
    public function getAff()
    {
        return ($this->aff);
    }

    /**
     * Get supannEntitePrimaryAffectation
     *
     */
    public function getPrimAff()
    {
        return ($this->primaff);
    }
 /**
  * Get member
  *
 */
 public function getMember()
 {
    return ($this->member);
 } 
 
 /**
  * Get admin
  *
 */
 public function getAdmin()
 {
    return ($this->admin);
 } 
  
}