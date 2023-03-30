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

namespace App\Entity;


use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;


class User {

  protected $uid;
  protected $sn;
  protected $displayname;
  protected $mail;
  protected $tel;
  protected $comp;
  protected $aff;
  protected $primaff;
  protected $campus;
  protected $site;
  protected $memberof;
  protected $adminof;
  protected $memberships;
  protected $exacte;

  public function __construct()
  {
      $this->memberships = new ArrayCollection();
  }
    
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
  * Set sn
  *
  * @param string $sn
 */
 public function setSn($sn)
 {
    $this->sn = $sn;
 }
 
 /**
  * Set displayName
  *
  * @param string $name
 */
 public function setDisplayname($name)
 {
    $this->displayname = $name;
 }
 /**
  * Set mail
  *
  * @param string $mail
 */
 public function setMail($mail)
 {
    $this->mail = $mail;
 } 

 /**
  * Set telephone number
  *
  * @param string $tel
 */
 public function setTel($tel)
 {
    $this->tel = $tel;
 } 
 /**
  * Set amucomposante
  *
  * @param string $comp
 */
 public function setComp($comp)
 {
    $this->comp = $comp;
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
 * Set amucampus
 *
 * @param string $campus
 */
 public function setCampus($campus)
 {
    $this->campus = $campus;
 }

 /**
  * Set amusite
  *
  * @param string $site
  */
  public function setSite($site)
  {
     $this->site = $site;
  }

    /**
  * Set memberof
  *
  * @param string $memberof
 */
 public function setMemberof($memberof)
 {
    $this->memberof = $memberof;
 } 
 
 /**
  * Set adminof
  *
  * @param string $adminof
 */
 public function setAdminof($adminof)
 {
    $this->adminof = $adminof;
 } 
 
 /**
  * Set exacte
  *
  * @param bool $exacte
 */
 public function setExacte($exacte)
 {
    $this->exacte = $exacte;
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
  * Get sn
  *
 */
 public function getSn()
 {
    return($this->sn);
 }
 
 /**
  * Get description
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
  * Get telephone number
  *
 */
 public function getTel()
 {
    return ($this->tel);
 } 

 /**
  * Get amuComposante
  *
 */
 public function getComp()
 {
    return ($this->comp);
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
 * Get amuCampus
 *
 */
public function getCampus()
 {
    return ($this->campus);
 }

/**
 * Get amuCampus
 *
 */
public function getSite()
 {
    return ($this->site);
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
  * Get getAdminof
  *
 */
 public function getAdminof()
 {
    return ($this->adminof);
 } 

 public function getMemberships()
 {
     return $this->memberships;
 }

 public function setMemberships(ArrayCollection $memberships)
 {
     $this->memberships = $memberships;
 }
 
 public function getExacte()
 {
     return $this->exacte;
 }
}