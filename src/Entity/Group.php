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


class Group {

  protected $cn;
  protected $etages;
  protected $nbetages;
  protected $lastopen;
  protected $lastnbetages;
  protected $description;
  protected $members;
  protected $amugroupadmin;
  protected $amugroupfilter;
  protected $droits;
  protected $flag;
  
public function __construct()
  {
      $this->members = new ArrayCollection();
  }  

 /**
  * Set cn
  *
  * @param string $cn
 */
 public function setCn($cn)
 {
    $this->cn = $cn;
 }
 /**
  * Set description
  *
  * @param string $description
 */
 public function setDescription($desc)
 {
    $this->description = $desc;
 }
 /**
  * Set amugroupfilter
  *
  * @param string $amugroupfilter
 */
 public function setAmugroupfilter($amugroupfilter)
 {
    $this->amugroupfilter = $amugroupfilter;
 } 
 /**
  * Set members
  *
 */
 public function setMembers(ArrayCollection $members)
 {
    $this->members = $members;
 } 
 
 
 /**
  * Set amugroupadmin
  *
  * @param string $amugroupadmin
 */
 public function setAmugroupadmin($amugroupadmin)
 {
    $this->amugroupadmin = $amugroupadmin;
 } 

 public function setDroits($droits)
 {
    $this->droits = $droits;
 } 
 
 public function setEtages($etages)
 {
    $this->etages = $etages;
 } 
 
 public function setNbetages($nbetages)
 {
    $this->nbetages = $nbetages;
 }
 
 public function setLastopen($lastopen)
 {
    $this->lastopen = $lastopen;
 }
 
 public function setLastnbetages($lastnbetages)
 {
    $this->lastnbetages = $lastnbetages;
 }

public function setFlag($flag)
{
    $this->flag = $flag;
}

 /**
  * Set cn
  *
  * @param string $cn
 */
 public function getCn()
 {
    return($this->cn);
 }
 /**
  * Set description
  *
  * @param string $description
 */
 public function getDescription()
 {
    return ($this->description);
 }
 /**
  * Set amugroupfilter
  *
  * @param string $amugroupfilter
 */
 public function getAmugroupfilter()
 {
    return ($this->amugroupfilter);
 } 

 public function getMembers()
 {
    return($this->members);
 }
 
 public function getAmugroupadmin()
 {
    return($this->amugroupadmin);
 }
 
 public function getDroits()
 {
    return($this->droits);
 }
 
 public function getEtages()
 {
    return($this->etages);
 }
 
 public function getNbetages()
 {
    return($this->nbetages);
 }
 
 public function getLastopen()
 {
    return($this->lastopen);
 }
 
 public function getLastnbetages()
 {
    return($this->lastnbetages);
 }

public function getFlag()
{
    return($this->flag);
}
  /**

 * @return  \Amu\AppBundle\Service\Ldap

 */

 public function infosGroupeLdap($param_cn, $param_desc, $param_groupfilter, $param_objectclass)
 {
   $addgroup = array();

   $addgroup[$param_cn] = $this->cn;
   $addgroup[$param_desc] = $this->description;
   if ($this->amugroupfilter != "") {
        $addgroup[$param_groupfilter] = $this->amugroupfilter;
   }

   foreach ($param_objectclass as $param_objectclass) {
       $addgroup['objectClass'][] = $param_objectclass;
   }

    return($addgroup);
 }
 
 public function infosGroupePriveLdap($uid) 
 {
   $infogroupe = array();
   $addgroup = array();

   // on préfixe le nom du groupe avec l'uid de l'utilisateur qui crée le groupe
   $addgroup['cn'] = 'amu:perso:'.$uid.':'.$this->cn;
   $addgroup['description'] = $this->description;
      
   $addgroup['objectClass'][0] = "groupOfNames";
   $addgroup['objectClass'][1] = "AMUGroup";
   $addgroup['objectClass'][2] = "top";
   
   $infogroupe['dn'] = "cn=amu:perso:".$uid.':'.$this->cn.", ou=private, ou=groups, dc=univ-amu, dc=fr";

   $infogroupe['infos'] = $addgroup;  

    return($infogroupe);
 }

  
};