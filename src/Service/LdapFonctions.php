<?php
/**
 * Created by PhpStorm.
 * User: peggy_fernandez
 * Date: 29/02/2016
 * Time: 16:25
 */

namespace App\Service;

use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Entry;

class LdapFonctions
{
    private $ldap;
    protected $base_dn;
    protected $config_users;
    protected $config_groups;
    protected $config_private;

    public function setLdap($ldap, $base_dn, $config_users, $config_groups, $config_private)
    {
        $this->ldap = $ldap;
        $this->base_dn = $base_dn;
        $this->config_users = $config_users;
        $this->config_groups = $config_groups;
        $this->config_private = $config_private;
    }

    public function recherche($filtre, $restriction, $flagGroup=0, $tri)
    {
        if($flagGroup) {
            // on cherche sur la branche des groupes
            $branch = $this->config_groups['group_branch'];
        }else {
            $branch = $this->config_users['people_branch'];
        }

        // Recherche avec les filtres et restrictions demandés
        $query = $this->ldap->query($branch.','.$this->base_dn, $filtre, array('filter' => $restriction));
        $arData = $query->execute()->toArray();

        if ($tri!="no") {
            // Tri des résultats
            if (sizeof($arData) > 1) {
                for ($i = 0; $i < sizeof($arData); $i++) {
                    $index = $arData[$i];
                    $j = $i;
                    $is_greater = true;
                    while ($j > 0 && $is_greater) {
                        //create comparison variables from attributes:
                        $a = $b = null;

                        $a .= strtolower($arData[$j - 1]->getAttribute($tri)[0]);
                        $b .= strtolower($index->getAttribute($tri)[0]);
                        if (strlen($a) > strlen($b))
                            $b .= str_repeat(" ", (strlen($a) - strlen($b)));
                        if (strlen($b) > strlen($a))
                            $a .= str_repeat(" ", (strlen($b) - strlen($a)));

                        // do the comparison
                        if ($a > $b) {
                            $is_greater = true;
                            $arData[$j] = $arData[$j - 1];
                            $j = $j - 1;
                        } else {
                            $is_greater = false;
                        }
                    }

                    $arData[$j] = $index;
                }
            }
        }
        return $arData;

    }

    /**
     * Récupération des infos d'un user
     */
    public function getInfosUser($uid) {
        $filtre = $this->config_users['login']."=" . $uid;
        $restriction = array($this->config_users['login'], $this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'], $this->config_users['name'], $this->config_users['primaff'], $this->config_users['aff']);
        $result = $this->recherche($filtre, $restriction, 0, $this->config_users['login']);
        return $result;
    }

    /**
     * Récupération des membres d'un groupe + infos des membres
     */
    public function getMembersGroup($groupName) {
        $filtre = $this->config_groups['memberof']."=".$this->config_groups['cn']."=" . $groupName . ", ".$this->config_groups['group_branch'].", ".$this->base_dn;
        $restriction = array($this->config_users['login'], $this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'], $this->config_users['name'], $this->config_users['primaff'], $this->config_users['aff']);
        $result = $this->recherche($filtre, $restriction, 0,"no");
        return $result;
    }

    /**
     * Récupération des admins d'un groupe + infos des membres
     */
    public function getAdminsGroup($groupName) {
        $filtre = $this->config_groups['cn']."=". $groupName ;
        $restriction = array($this->config_groups['groupadmin']);
        $result = $this->recherche($filtre, $restriction, 1, "no");
        return $result;
    }

    /**
     * Ajouter un membre dans un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function addMemberGroup($dn_group, $arUserUid) {
        $arDnMembers = array();
        foreach ($arUserUid as $uid)
        {
            $arDnMembers[] = $this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base_dn;
        }

        // Entry manager
        $entryManager = $this->ldap->getEntryManager();

        // Finding and updating group
        $pos = strpos($dn_group, "ou=");
        $cn = substr($dn_group, 0, $pos-2);
        $base = substr($dn_group, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array('description')));
        $result = $query->execute();
        $entry = $result[0];
        try {
            $entryManager->addAttributeValues($entry, $this->config_groups['member'], $arDnMembers);
        }catch (\Exception $e) {
            return(false);
        }
        return(true);

    }

    /**
     * Supprimer un membre d'un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function delMemberGroup($dn_group, $arUserUid) {
        $arDnMembers = array();
        foreach ($arUserUid as $uid)
        {
            $arDnMembers[] = $this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base_dn;
        }

        // Entry manager
        $entryManager = $this->ldap->getEntryManager();

        // Finding and updating group
        $pos = strpos($dn_group, "ou=");
        $cn = substr($dn_group, 0, $pos-2);
        $base = substr($dn_group, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array('description')));
        $result = $query->execute();
        $entry = $result[0];
        try {
            $entryManager->removeAttributeValues($entry, $this->config_groups['member'], $arDnMembers);
        }catch (\Exception $e) {
            return(false);
        }
        return(true);

    }

    /**
     * Ajouter un administrateur dans un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function addAdminGroup($dn_group, $arUserUid) {
        $arDnAdmins = array();
        foreach ($arUserUid as $uid)
        {
            $arDnAdmins[] = $this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base_dn;
        }
        // Entry manager
        $entryManager = $this->ldap->getEntryManager();

        // Finding and updating group
        $pos = strpos($dn_group, "ou=");
        $cn = substr($dn_group, 0, $pos-2);
        $base = substr($dn_group, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array('description')));
        $result = $query->execute();
        $entry = $result[0];
        try {
            $entryManager->addAttributeValues($entry, $this->config_groups['groupadmin'], $arDnAdmins);
        }catch (\Exception $e) {
            return(false);
        }
        return(true);

    }

    /**
     * Supprimer un membre d'un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function delAdminGroup($dn_group, $arUserUid) {

        $arDnAdmins = array();
        foreach ($arUserUid as $uid)
        {
            $arDnAdmins[] = $this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base_dn;
        }
        // Entry manager
        $entryManager = $this->ldap->getEntryManager();

        // Finding and updating group
        $pos = strpos($dn_group, "ou=");
        $cn = substr($dn_group, 0, $pos-2);
        $base = substr($dn_group, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array('description')));
        $result = $query->execute();
        $entry = $result[0];
        try {
            $entryManager->removeAttributeValues($entry, $this->config_groups['groupadmin'], $arDnAdmins);
        }catch (\Exception $e) {
            return(false);
        }
        return(true);

    }

    /**
     * Supprimer le amugroupfilter d'un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function delAmuGroupFilter($dn_group, $filter) {

        // Entry manager
        $entryManager = $this->ldap->getEntryManager();

        // Finding and updating group
        $pos = strpos($dn_group, "ou=");
        $cn = substr($dn_group, 0, $pos-2);
        $base = substr($dn_group, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array('description')));
        $result = $query->execute();
        $entry = $result[0];
        try {
            $entryManager->removeAttributeValues($entry, $this->config_groups['groupfilter'], [$filter]);
        }catch (\Exception $e) {
            return(false);
        }
        return(true);

    }

    /**
     * Récupérer le amugroupfilter d'un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function getAmuGroupFilter($cn_group) {

        $filtre = $this->config_groups['cn']."=" . $cn_group;
        $result = $this->recherche($filtre, array($this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        if (null !== $result[0]->getAttribute($this->config_groups['groupfilter']))
            $amugroupfilter = $result[0]->getAttribute($this->config_groups['groupfilter'])[0];
        else
            $amugroupfilter = "";

        return $amugroupfilter;
    }

    public function createGroupeLdap($dn, $groupeinfo)
    {
        $entry = new Entry($dn, $groupeinfo);
        $entryManager = $this->ldap->getEntryManager();

        // Creating a new entry
        try {
            $entryManager->add($entry);
        }catch (\Exception $e) {
            return(false);
        }


        return(true);

    }

    public function deleteGroupeLdap($cn)
    {
        $dn = $this->config_groups['cn']."=".$cn.",".$this->config_groups['group_branch'].",".$this->base_dn;
        $entryManager = $this->ldap->getEntryManager();

        try {
            $entryManager->remove(new Entry($dn));
        }catch (\Exception $e) {
            return(false);
        }

        return(true);

    }

    public function renameGroupeLdap($dn, $cn_new)
    {
        // Finding and updating group
        $pos = strpos($dn, "ou=");
        $cn = substr($dn, 0, $pos-2);
        $base = substr($dn, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array('description')));
        $result = $query->execute();
        $entry = $result[0];
        $entryManager = $this->ldap->getEntryManager();

        $rdn_new = $this->config_groups['cn']."=".$cn_new;
        try {
            $entryManager->rename($entry, $rdn_new);
        }catch (\Exception $e) {
            return(false);
        }

        return(true);

    }

    /**
     * Supprimer le amugroupfilter et la description d'un groupe
     * @return  \Amu\AppBundle\Service\Ldap
     */
    public function modGroup($dn_group, $desc, $filter) {

        // Entry manager
        $entryManager = $this->ldap->getEntryManager();

        // Finding and updating group
        $pos = strpos($dn_group, "ou=");
        $cn = substr($dn_group, 0, $pos-2);
        $base = substr($dn_group, $pos);
        $query = $this->ldap->query($base, $cn, array('filter' => array($this->config_groups['desc'], $this->config_groups['groupfilter'])));
        $result = $query->execute();
        $entry = $result[0];
        try {
            $entry->setAttribute($this->config_groups['desc'], [$desc]);
            $entryManager->update($entry);
            if ($filter !== null) {
                // Test amu groupfilter :
                // on verifie que le filtre ldap est valide
                $pos = strpos($filter, "dbi");
                if ($pos === 0) {
                    // C'est un filtre pour bdd, on ne vérifie pas plus
                    try {
                        $entry->setAttribute($this->config_groups['groupfilter'], [$filter]);
                        $entryManager->update($entry);
                        return (true);
                    }catch (\Exception $e) {
                        return(false);
                    }
                } else {
                    // c'est un filtre ldap, on teste
                    $queryTest = $this->ldap->query($this->base_dn, $filter);
                    try {
                        $resultTest = $queryTest->execute();
                        if (sizeof($resultTest) > 0) {
                            $entry->setAttribute($this->config_groups['groupfilter'], [$filter]);
                            $entryManager->update($entry);
                        } else {
                            return (2);
                        }
                    } catch (\Exception $e) {
                        return (2);
                    }
                }
            }
        }catch (\Exception $e) {
            return(false);
        }
        return(true);

    }

    /**
     * Tester la validité amuGroupFilter
     */
    public function testAmugroupfilter($filter) {
        // On vérifie si filtre LDAP pour filtre base de données
        $pos = strpos($filter, "dbi");
        if ($pos === 0) {
            // filtre BDD, on ne vérifie pas la validité
            return true;
        } else {
            // filtre LDAP, on teste si le filtre est ok
            $queryTest = $this->ldap->query($this->base_dn, $filter);
            try {
                $resultTest = $queryTest->execute();
                if (sizeof($resultTest) > 0) {
                    return true;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }

    }

    public function getUidFromMail($mail, $restriction = array("uid", "displayName", "sn", "mail", "telephonenumber", "memberof")) {
	$filtre = "(&(".$this->config_users['mail']."=" . $mail . ")" . $this->config_users['filter'] . ")";
        $AllInfos = array();
        $AllInfos = $this->recherche($filtre, $restriction, 0, $this->config_users['mail']);

        return $AllInfos;
    }

    public function TestUid($uid, $restriction = array("uid", "sn", "displayName", "mail", "telephonenumber", "memberof")) {
        $filtre = "(&(".$this->config_users['login']."=" . $uid . ")" . $this->config_users['filter'] . ")";
        $AllInfos = array();
        $AllInfos = $this->recherche($filtre, $restriction, 0, $this->config_users['login']);

        return $AllInfos;
    }
}
