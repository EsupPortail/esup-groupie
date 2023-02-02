<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Ldap;
use Doctrine\Common\Collections\ArrayCollection;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\Membership;
use App\Form\UserEditType;
use App\Form\UserSearchType;
use App\Form\UserMultipleType;

use App\Service\LdapFonctions;


/**
 * user controller
 * @Route("/user")
 * 
 */
class UserController extends AbstractController {

    protected $config_logs;
    protected $config_users;
    protected $config_groups;
    protected $config_private;
    protected $base;

    /**
     * Fonction d'initialisation : récupère les paramètres définis dans le fichier config.yml
     *
     */
    protected function init_config()
    {
        if (!isset($this->config_logs))
            $this->config_logs = $this->getParameter('logs');
        if (!isset($this->config_users))
            $this->config_users = $this->getParameter('users');
        if (!isset($this->config_groups))
            $this->config_groups = $this->getParameter('groups');
        if (!isset($this->config_private))
            $this->config_private = $this->getParameter('private');
        if (!isset($this->base)) {
            $profil = $this->getParameter('ldap_param');
            $this->base = $profil['base_dn'];
        }
    }

    /**
     * Edite les droits d'un utilisateur issu du LDAP.
     *
     * @Route("/update/{uid}", name="user_update")
     * @Template("User/update.html.twig")
     */
    public function updateAction(LdapFonctions $ldapfonctions, Request $request, $uid)
    {
        $this->init_config();

        // Accès autorisé pour les membres
        $flag= "nok";
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_MEMBRE'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Dans le cas d'un gestionnaire
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
            // Recup des groupes dont l'utilisateur est admin
            $arDataAdminLogin = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
            for($i=0;$i<sizeof($arDataAdminLogin);$i++)
            {
                $tab_cn_admin_login[$i] = $arDataAdminLogin[$i]->getAttribute($this->config_groups['cn'])[0];
            }
        }
        
        // Recherche des utilisateurs dans le LDAP
        $arData = $ldapfonctions->recherche($this->config_users['login']."=".$uid, array($this->config_users['login'], $this->config_users['name'],$this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'], $this->config_groups['memberof']), 0, $this->config_users['login']);
            
        // Initialisation de l'utilisateur sur lequel on souhaite modifier les appartenances
        $user = new User();
        $user->setUid($uid);
        $user->setDisplayname($arData[0]->getAttribute($this->config_users['displayname'])[0]);
        if (isset($arData[0]->getAttribute($this->config_users['mail'])[0]))
            $user->setMail($arData[0]->getAttribute($this->config_users['mail'])[0]);
        $user->setSn($arData[0]->getAttribute($this->config_users['name'])[0]);
        if (isset($arData[0]->getAttribute($this->config_users['tel'])[0]))
            $user->setTel($arData[0]->getAttribute($this->config_users['tel'])[0]);
        $tab_memberof = $arData[0]->getAttribute($this->config_groups['memberof']);
        $tab = array_splice($tab_memberof, 1);
        $tab_cn = array(); 
        $nb_public=0;
        foreach($tab as $dn) {
            // on ne récupère que les groupes publics
            if (!strstr($dn, $this->config_private['private_branch'])) {
                $tab_cn[] = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:_-]{1,}))(,ou=.*)/", "$3", strtolower($dn));
                $nb_public++;
            }
        }
        $user->setMemberof($tab_cn); 
        
        // User initial pour détecter les modifications
        $userini = new User();
        $userini->setUid($uid);
        $userini->setDisplayname($arData[0]->getAttribute($this->config_users['displayname'])[0]);
        if (isset($arData[0]->getAttribute($this->config_users['mail'])[0]))
            $userini->setMail($arData[0]->getAttribute($this->config_users['mail'])[0]);
        $userini->setSn($arData[0]->getAttribute($this->config_users['name'])[0]);
        if (isset($arData[0]->getAttribute($this->config_users['tel'])[0]))
            $userini->setTel($arData[0]->getAttribute($this->config_users['tel'])[0]);
        $userini->setMemberof($tab_cn); 
        
        // Récupération des groupes dont l'utilisateur est admin
        $arDataAdmin = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        $flagMember = array();
        for($i=0;$i<sizeof($arDataAdmin);$i++)
            $flagMember[$i] = FALSE;
        
        // Initialisation des tableaux d'entités
        $groups = new ArrayCollection();
        $memberships = new ArrayCollection();
        $membershipsini = new ArrayCollection();
        
        // Gestion des groupes publics dont l'utilisateur est membre
        for($i=0; $i<$nb_public;$i++){
            $membership = new Membership();
            $membership->setGroupname($tab_cn[$i]);
            $membership->setGroupname($tab_cn[$i]);
            $membership->setMemberof(TRUE);
            $membership->setDroits('Aucun');
            
            // Idem pour membershipini
            $membershipini = new Membership();
            $membershipini->setGroupname($tab_cn[$i]);
            $membershipini->setMemberof(TRUE);
            $membershipini->setDroits('Aucun'); 
            // on teste si l'utilisateur est aussi admin du groupe
            for ($j=0; $j<sizeof($arDataAdmin);$j++) {
                if ($arDataAdmin[$j]->getAttribute($this->config_groups['cn'])[0] == $tab_cn[$i]) {
                    $membership->setAdminof(TRUE);
                    $membershipini->setAdminof(TRUE);
                    $flagMember[$j] = TRUE;
                    break;
                }
                else {
                    $membership->setAdminof(FALSE);
                    $membershipini->setAdminof(FALSE);
                }
            }
            
            // Par défaut, aucun droit
            $membership->setDroits('Aucun');
            $membershipini->setDroits('Aucun'); 
                            
            // Gestion droits pour un membre de la DOSI
            if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) {
                $membership->setDroits('Voir');
                $membershipini->setDroits('Voir');  
            }
            
            // Gestion droits pour un gestionnaire
            if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
                foreach($tab_cn_admin_login as $cn) {
                    if ($cn==$tab_cn[$i]) {
                        $membership->setDroits('Modifier');
                        $membershipini->setDroits('Modifier');
                        break;
                    }
                }
            }
            
            // Gestion droits pour un admin de l'appli
            if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                $membership->setDroits('Modifier');
                $membershipini->setDroits('Modifier');  
            }
            
            $memberships[$i] = $membership;
            $membershipsini[$i] = $membershipini;
        }
        
        // Gestion des groupes dont l'utilisateur est seulement admin
        for($i=0;$i<sizeof($arDataAdmin);$i++) {
            if ($flagMember[$i]==FALSE) {
                // on ajoute le groupe pour l'utilisateur
                $membership = new Membership();
                $membership->setGroupname($arDataAdmin[$i]->getAttribute($this->config_groups['cn'])[0]);
                $membership->setMemberof(FALSE);
                $membership->setAdminof(TRUE);
                $membership->setDroits('Aucun');
                
                // Idem pour membershipini
                $membershipini = new Membership();
                $membershipini->setGroupname($arDataAdmin[$i]->getAttribute($this->config_groups['cn'])[0]);
                $membershipini->setMemberof(FALSE);
                $membershipini->setAdminof(TRUE);
                $membershipini->setDroits('Aucun');
                
                // Gestion droits pour un membre de la DOSI
                if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) {
                    $membership->setDroits('Voir');
                    $membershipini->setDroits('Voir');  
                }
            
                // Gestion droits pour un gestionnaire
                if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
                    foreach($tab_cn_admin_login as $cn) {
                        if ($cn==$arDataAdmin[$i]->getAttribute($this->config_groups['cn'])[0]) {
                            $membership->setDroits('Modifier');
                            $membershipini->setDroits('Modifier');
                            break;
                        }
                    }
                }
                // Gestion droits pour un admin de l'appli
                if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                    $membership->setDroits('Modifier');
                    $membershipini->setDroits('Modifier');
                }
            
                $memberships[] = $membership;
                $membershipsini[] = $membershipini;
            }
            
        }
        
        $user->setMemberships($memberships);
        $userini->setMemberships($membershipsini);

        // Création du formulaire de mise à jour de l'utilisateur
        $editForm = $this->createForm(UserEditType::class, $user, array(
            'action' => $this->generateUrl('user_update', array('uid'=> $uid)),
            'method' => 'POST',));
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $userupdate = new User();
            // Récupération des données du formulaire
            $userupdate = $editForm->getData();
             
            // Log modif de groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
            
            // Traitement des données issues de la récup du formulaire
            $m_update = new ArrayCollection();      
            $m_update = $userupdate->getMemberships();
            for ($i=0; $i<sizeof($m_update); $i++) {
                $memb = $m_update[$i];
                $dn_group = $this->config_groups['cn']."=" . $memb->getGroupname() . ", ".$this->config_groups['group_branch'].",".$this->base;
                $c = $memb->getGroupname();
                
                // Si l'utilisateur logué à les droits en modification
                if ($memb->getDroits()=='Modifier') {
                    // Si changement, on modifie dans le ldap
                    if ($memb->getMemberof() != $membershipsini[$i]->getMemberof()) {
                        if ($memb->getMemberof()) {
                            $r = $ldapfonctions->addMemberGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "add_member by $adm : group : $c, user : $uid");
                            }
                            else {
                                // Log erreur
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de l\'ajout des droits \'membre\'');
                                syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $c, user : $uid");
                            }              
                        }
                        else {
                            $r = $ldapfonctions->delMemberGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "del_member by $adm : group : $c, user : $uid");
                            }
                            else {
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de la suppression des droits \'membre\'');
                                syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $c, user : $uid");
                            }
                        }
                    }
                    // Traitement des admins
                    // Idem si changement des droits
                    if ($memb->getAdminof() != $membershipsini[$i]->getAdminof()) {
                        if ($memb->getAdminof()) {
                            $r = $ldapfonctions->addAdminGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "add_admin by $adm : group : $c, user : $uid");
                            }
                            else {
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de l\'ajout des droits \'admin\'');
                                syslog(LOG_ERR, "LDAP ERROR : add_admin by $adm : group : $c, user : $uid");
                            }
                        }
                        else {
                            $r = $ldapfonctions->delAdminGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "del_admin by $adm : group : $c, user : $uid");
                            }
                            else {
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de la suppression des droits \'admin\'');
                                syslog(LOG_ERR, "LDAP ERROR : del_admin by $adm : group : $c, user : $uid");
                            }
                        }
                    }
                }
            }
            // Ferme fichier log
            closelog();
            
            // Afiichage du message de notification
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');
            
            // Retour à l'affichage user_update
            return $this->redirect($this->generateUrl('user_update', array('uid'=>$uid)));
        }

        return array(
            'user'      => $user,
            'form'   => $editForm->createView(),
        );
    }
  
     
    /**
     * Ajoute les droits d'un utilisateur à un groupe.
     *
     * @Route("/add/{uid}/{cn}/{liste}",name="user_add")
     * @Template("User/searchadd.html.twig")
     */
    public function addAction(LdapFonctions $ldapfonctions, Request $request, $uid='', $cn='', $liste='') {
        $this->init_config();

        // Récupération utilisateur
        $user = new User();
        $user->setUid($uid);

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Vérification des droits
        $flag = "nok";
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
            // Recup des groupes dont l'utilisateur est admin
            $arDataAdminLogin = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
            for($i=0;$i<sizeof($arDataAdminLogin);$i++)
            {
                if ($cn==$arDataAdminLogin[$i]->getAttribute($this->config_groups['cn'])[0]) {
                    $flag = "ok";
                    break;
                }
            }
        }
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        // Recherche user
        $arDataUser = $ldapfonctions->recherche($this->config_users['login']."=".$uid, array($this->config_users['displayname'], $this->config_groups['memberof'], $this->config_users['login']), 0, $this->config_users['login']);
                
        // Test de la validité de l'uid
        if ($arDataUser[0]->getAttribute($this->config_users['login'])[0] == '') {
            $this->get('session')->getFlashBag()->add('flash-notice', 'L\'uid n\'est pas valide');
            return $this->redirect($this->generateUrl('user_search', array('opt' => 'add', 'cn'=>$cn)));
        }
        else {
            $user->setDisplayname($arDataUser[0]->getAttribute($this->config_users['displayname'])[0]);
            $tab_membersof = $arDataUser[0]->getAttribute($this->config_groups['memberof']);
            $tab = array_splice($tab_membersof, 1);
            // Tableau des groupes de l'utilisateur
            $tab_cn = array();
            foreach($tab as $dn)
                $tab_cn[] = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", strtolower($dn));

            // Recherche des admins du groupe dans le LDAP
            $arAdmins = $ldapfonctions->getAdminsGroup($cn);

            // User initial pour détecter les modifications
            $userini = new User();
            $userini->setUid($uid);
            $userini->setDisplayname($arDataUser[0]->getAttribute($this->config_users['displayname'])[0]);

            // on remplit l'objet user avec les droits courants sur le groupe
            $memberships = new ArrayCollection();
            $membership = new Membership();
            $membership->setGroupname($cn);

            // Idem pour userini
            $membershipsini = new ArrayCollection();
            $membershipini = new Membership();
            $membershipini->setGroupname($cn);

            // Droits "membre"
            foreach($tab_cn as $cn_g) {
                if ($cn==$cn_g) {
                    //$membership->setMemberof(TRUE);
                    $membershipini->setMemberof(TRUE);
                    break;
                }
                else {
                    //$membership->setMemberof(FALSE);
                    $membershipini->setMemberof(FALSE);
                }
                // Par défaut, on présente la case membre cochée
                $membership->setMemberof(TRUE);
            }
            // Droits "admin"
            if (isset($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])["count"])){
                for ($j=0; $j<sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])); $j++) {
                    // récupération des uid des admin du groupe
                    $uid_admins = preg_replace("/(".$this->config_users['login']."=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", strtolower($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])[$j]));
                    if ($uid == $uid_admins) {
                        $membership->setAdminof(TRUE);
                        $membershipini->setAdminof(TRUE);
                        break;
                    }
                    else {
                        $membership->setAdminof(FALSE);
                        $membershipini->setAdminof(FALSE);
                    }
                }
            }
            $memberships[0] = $membership;
            $user->setMemberships($memberships);       

            // Idem userini
            $membershipsini[0] = $membershipini;
            $userini->setMemberships($membershipsini);       

            // Création du formulaire d'ajout
            $editForm = $this->createForm(UserEditType::class, $user, array(
                'action' => $this->generateUrl('user_add', array('uid'=> $uid, 'cn' => $cn)),
                'method' => 'GET',
            ));
            $editForm->handleRequest($request);

            if ($editForm->isSubmitted() && $editForm->isValid()) {
                $userupdate = new User();
                // Récupération des données du formulaire
                $userupdate = $editForm->getData();

                // Log modif de groupe
                openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
                $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");

                $m_update = new ArrayCollection();      
                $m_update = $userupdate->getMemberships();

                for ($i=0; $i<sizeof($m_update); $i++) {
                    $memb = $m_update[$i];
                    $dn_group = $this->config_groups['cn']."=" . $cn . ", ".$this->config_groups['group_branch'].", ".$this->base;

                    // Traitement des membres
                    // Si modification des droits, on modifie dans le ldap
                    if ($memb->getMemberof() != $membershipsini[$i]->getMemberof()) {
                        if ($memb->getMemberof()) {
                            $r = $ldapfonctions->addMemberGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $uid");
                            }
                            else {
                                // Affichage notification
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de l\'ajout des droits \'membre\' '.$cn);
                                syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $uid");
                            }
                        }
                        else {
                            $r = $ldapfonctions->delMemberGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "del_member by $adm : group : $cn, user : $uid");
                            }
                            else {
                                // Affichage notification
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de la suppression des droits \'membre\'');
                                syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $cn, user : $uid");
                            }
                        }
                    }

                    // Traitement des admins
                    // Si modification des droits, on modifie dans le ldap
                    if ($memb->getAdminof() != $membershipsini[$i]->getAdminof()) {
                        if ($memb->getAdminof()) {
                            $r = $ldapfonctions->addAdminGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "add_admin by $adm : group : $cn, user : $uid");
                            }
                            else {
                                // Affichage notification
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de l\'ajout des droits \'admin\'');
                                syslog(LOG_ERR, "LDAP ERROR : add_admin by $adm : group : $cn, user : $uid");
                            }
                        }
                        else {
                            $r = $ldapfonctions->delAdminGroup($dn_group, array($uid));
                            if ($r) {
                                // Log modif
                                syslog(LOG_INFO, "del_admin by $adm : group : $cn, user : $uid");
                            }
                            else {
                                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de la suppression des droits \'admin\'');
                                syslog(LOG_ERR, "LDAP ERROR : del_admin by $adm : group : $cn, user : $uid");
                            }
                        }
                    }
                }
                // Affichage notification
                $this->get('session')->getFlashBag()->add('flash-notice', 'Les droits ont bien été modifiés');

                // Ferme fichier log
                closelog();

                // Retour à la page update d'un groupe
                return $this->redirect($this->generateUrl('group_update', array('cn'=>$cn)));
            }
        }
        
        return array(
            'user'      => $user,
            'cn' => $cn,
            'form'   => $editForm->createView(),
            'liste' => $liste
        );
    }
     
    /**
     * Ajoute les droits d'un utilisateur à un groupe.
     *
     * @Route("/addprivate/{uid}/{cn}/{opt}",name="user_add_private")
     */
    public function addprivateAction(LdapFonctions $ldapfonctions, Request $request, $uid='', $cn='', $opt='liste') {
        $this->init_config();

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Accès autorisé pour les gestionnaires et les admins
        $flag= "nok";
        if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_MEMBRE'))){
            // Recup des groupes dont l'utilisateur est admin
            $arDataAdminLogin = $ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=".$this->config_private['prefix'].":".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").":*))", array($this->config_groups['cn'], $this->config_groups['desc']) , 1, $this->config_groups['cn']);
            for($i=0;$i<sizeof($arDataAdminLogin);$i++) {
                if ($cn==$arDataAdminLogin[$i]->getAttribute($this->config_groups['cn'])[0]) {
                    $flag = "ok";
                    break;
                }
            }
        }
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            $flag = "ok";

        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        // Récupération utilisateur
        $user = new User();
        $user->setUid($uid);

        $arDataUser = $ldapfonctions->recherche($this->config_users['login']."=".$uid, array($this->config_users['displayname'], $this->config_groups['memberof'], $this->config_users['login']), 0, $this->config_users['login']);
        
        // Test de la validité de l'uid
        if ($arDataUser[0]->getAttribute($this->config_users['login'])[0] == '') {
            $this->get('session')->getFlashBag()->add('flash-notice', 'L\'identifiant n\'est pas valide');
            return $this->redirect($this->generateUrl('user_search', array('opt' => 'addprivate', 'cn'=>$cn)));
        }
        else {
            $user->setDisplayname($arDataUser[0]->getAttribute($this->config_users['displayname'])[0]);
            $tab_groups = $arDataUser[0]->getAttribute($this->config_groups['memberof']);
            $tab = array_splice($tab_groups, 1);
            // Tableau des groupes de l'utilisateur
            $tab_cn = array();
            foreach($tab as $dn)
                $tab_cn[] = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", strtolower($dn));

            // On teste si le user est déjà membre du groupe
            $FlagMemb = FALSE;
            foreach($tab_cn as $cn_g) {
                if ($cn==$cn_g) {
                    $FlagMemb = TRUE;
                    break;
                }
            }

            if (!$FlagMemb) {
                // Si le user n'est pas membre, on le rajoute

                // Log modif de groupe
                openlog("groupie-2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
                $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");

                $dn_group = $this->config_groups['cn']."=" . $cn . ", ".$this->config_private['private_branch'].", ".$this->config_groups['group_branch'].",".$this->base;

                $r = $ldapfonctions->addMemberGroup($dn_group, array($uid));
                if ($r) {
                    // Notification
                    $this->get('session')->getFlashBag()->add('flash-notice', 'Les droits ont bien été ajoutés');
                    // Log modif
                    syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $uid");
                }
                else {
                    // Notification
                    $this->get('session')->getFlashBag()->add('flash-error', 'Erreur lors de l\'ajout des droits');
                    syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $uid");
                }

                // Ferme fichier log
                closelog();

                // Retour à la page update d'un groupe
                return $this->redirect($this->generateUrl('private_group_update', array('cn'=>$cn)));
            }
            else {
                $this->get('session')->getFlashBag()->add('flash-notice', 'Cette personne est déjà membre du groupe');
                return $this->redirect($this->generateUrl('user_search', array('opt' => 'addprivate', 'cn'=>$cn)));
            }
        }
    }
    
    /**
     * Voir les appartenances et droits d'un utilisateur.
     *
     * @Route("/see/{uid}", name="see_user")
     * @Template()
     */
    public function seeAction(LdapFonctions $ldapfonctions, Request $request, $uid)
    {
        $this->init_config();

        // Vérification des droits
        $flag = "nok";
        // Dans le cas DOSI
        if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) || (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        $membersof = array();
        $adminsof = array();
        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Recherche des groupes dont l'utilisateur est membre 
        $arData = $ldapfonctions->recherche($this->config_groups['member']."=".$this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        for ($i=0; $i<sizeof($arData); $i++) {
            // on ne récupere que les groupes publics
            if (!strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                $gr = new Group();
                $gr->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $gr->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
                $gr->setAmugroupfilter($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0]);
                $membersof[] = $gr;
            }
        }
                
        // Récupération des groupes dont l'utilisateur est admin
        $arDataAdmin=$this->getLdap()->arDatasFilter($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']));
        for ($i=0; $i<$arDataAdmin["count"]; $i++) {
            $gr_adm = new Group();
            $gr_adm->setCn($arDataAdmin[$i][$this->config_groups['cn']][0]);
            $gr_adm->setDescription($arDataAdmin[$i][$this->config_groups['desc']][0]);
            $gr_adm->setAmugroupfilter($arDataAdmin[$i][$this->config_groups['groupfilter']][0]);
            $adminsof[] = $gr_adm;
        }
        
        return array('uid' => $uid,
                    'nb_grp_membres' => $arData["count"], 
                    'grp_membres' => $membersof,
                    'nb_grp_admins' => $arDataAdmin["count"],
                    'grp_admins' => $adminsof);
    }
    
    /**
     * Voir les appartenances et droits d'un utilisateur.
     *
     * @Route("/seeprivate/{uid}", name="see_user_private")
     * @Template()
     */
    public function seeprivateAction(LdapFonctions $ldapfonctions, Request $request, $uid)
    {
        $this->init_config();

        // Vérification des droits
        $flag = "nok";
        // Dans le cas DOSI
        if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) || (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        $membersof = array();
        $propof = array();

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Recherche des groupes dont l'utilisateur est membre 
        $arData=$ldapfonctions->recherche($this->config_groups['member']."=".$this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        $nb_grp_memb = 0;

        for ($i=0; $i<sizeof($arData); $i++) {
            // on ne récupere que les groupes privés
            if (strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                $gr = new Group();
                $gr->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $gr->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
                $membersof[] = $gr;
                $nb_grp_memb++;
            }
        }
                
        // Récupération des groupes dont l'utilisateur est propriétaire
        $arDataProp=$ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=".$this->config_private['prefix'].":".$uid.":*))",array($this->config_groups['cn'],$this->config_groups['desc']), 1, $this->config_groups['cn']);

        for ($i=0; $i<sizeof($arDataProp); $i++) {
            $gr_prop = new Group();
            $gr_prop->setCn($arDataProp[$i]->getAttribute($this->config_groups['cn'])[0]);
            $gr_prop->setDescription($arDataProp[$i]->getAttribute($this->config_groups['desc'])[0]);
            $propof[] = $gr_prop;
        }
        $nb_groups = sizeof($arDataProp);
        
        return array('uid' => $uid,
                    'nb_grp_membres' => $nb_grp_memb, 
                    'grp_membres' => $membersof,
                    'nb_grp_prop' => $nb_groups,
                    'grp_prop' => $propof);
    }
    
    /**
    * Recherche de personnes
    *
    * @Route("/search/{opt}/{cn}/{liste}",name="user_search")
    * @Template()
    */
    public function searchAction(LdapFonctions $ldapfonctions, Request $request, $opt='search', $cn='', $liste='') {
        $this->init_config();
        $usersearch = new User();
        $users = array();
        $u = new User();
        $u->setExacte(true);

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);
        
        // Création du formulaire de recherche
        $form = $this->createForm(UserSearchType::class,
            $u,
            array('action' => $this->generateUrl('user_search', array('opt'=>$opt, 'cn'=>$cn)),
                'method' => 'GET'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération des données du formulaire
            $usersearch = $form->getData();

            // On teste si on a qqchose dans le champ uid
            if ($usersearch->getUid()=='') {
                // si on a rien, on teste le nom
                // On teste si on fait une recherche exacte ou non
                if ($usersearch->getExacte()) {
                    $arData=$ldapfonctions->recherche("(&(".$this->config_users['name']."=".$usersearch->getSn().")".$this->config_users['filter'].")", array($this->config_users['login'], $this->config_users['name'],$this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'], $this->config_users['comp'], $this->config_users['aff'], $this->config_users['primaff'], $this->config_users['campus'], $this->config_users['site'], $this->config_groups['memberof']), 0, $this->config_users['login']);
                }
                else {
                    $arData=$ldapfonctions->recherche("(&(".$this->config_users['name']."=".$usersearch->getSn()."*)".$this->config_users['filter'].")", array($this->config_users['login'], $this->config_users['name'],$this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'], $this->config_users['comp'], $this->config_users['aff'], $this->config_users['primaff'], $this->config_users['campus'], $this->config_users['site'], $this->config_groups['memberof']), 0, $this->config_users['login']);
                }

                // on récupère la liste des uilisateurs renvoyés par la recherche
                $nb=0;
                for($i=0;$i<sizeof($arData);$i++) {
                    $data = $arData[$i];
                        
                    $user = new User();
                    $user->setUid($data->getAttribute($this->config_users['login'])[0]);
                    $user->setDisplayname($data->getAttribute($this->config_users['displayname'])[0]);
                    if (isset($data->getAttribute($this->config_users['mail'])[0]))
                        $user->setMail($data->getAttribute($this->config_users['mail'])[0]);
                    $user->setSn($data->getAttribute($this->config_users['name'])[0]);
                    if (isset($data->getAttribute($this->config_users['tel'])[0]))
                        $user->setTel($data->getAttribute($this->config_users['tel'])[0]);
                    if (isset($data->getAttribute($this->config_users['comp'])[0]))
                        $user->setComp($data->getAttribute($this->config_users['comp'])[0]);
                    if (isset($data->getAttribute($this->config_users['aff'])[0]))
                        $user->setAff($data->getAttribute($this->config_users['aff'])[0]);
                    if (isset($data->getAttribute($this->config_users['primaff'])[0]))
                        $user->setPrimAff($data->getAttribute($this->config_users['primaff'])[0]);
                    if (isset($data->getAttribute($this->config_users['campus'])[0]))
                        $user->setCampus($data->getAttribute($this->config_users['campus'])[0]);
                    if (isset($data->getAttribute($this->config_users['site'])[0]))
                        $user->setSite($data->getAttribute($this->config_users['site'])[0]);
                    $users[] = $user; 
                    $nb++;    
                }
                
                // Gestion des droits
                $droits = 'Aucun';
                // Droits DOSI seulement en visu
                if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) {
                    $droits = 'Voir';
                }
                if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) || (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')))  {
                    $droits = 'Modifier';
                }
                    
                // Mise en session des résultats de la recherche
                $this->get('session')->set('users', $users);
                    
                // Si on a un seul résultat de recherche, affichage direct de l'utilisateur concerné en fonction des droits
                if ($nb==1) {
                    if ($opt == 'searchprivate')
                    {
                        return $this->redirect($this->generateUrl('voir_user_private', array('uid' => $user->getUid()))); 
                    }
                    
                    return $this->redirect($this->generateUrl('user_update', array('uid' => $user->getUid())));
                }
                // Sinon, affichage du tableau d'utilisateurs
                return $this->render('User/search.html.twig',array('users' => $users, 'opt' => $opt, 'droits' => $droits, 'cn' => $cn, 'liste' => $liste));
            }
            else {
                // Recherche des utilisateurs dans le LDAP
                $arData=$ldapfonctions->recherche("(&(".$this->config_users['login']."=".$usersearch->getUid().")".$this->config_users['filter'].")", array($this->config_users['login'], $this->config_users['name'],$this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'],$this->config_users['comp'], $this->config_users['aff'], $this->config_users['primaff'], $this->config_users['campus'], $this->config_users['site'], $this->config_groups['memberof'], "amudatevalidation"), 0, $this->config_users['login']);
                
                // Test de la validité de l'uid
                if (null === ($arData[0]->getAttribute($this->config_users['login']))) {
                    $this->get('session')->getFlashBag()->add('flash-notice', 'L\'identifiant n\'est pas valide');
                }
                else {
                    $user = new User();
                    $user->setUid($usersearch->getUid());
                    $user->setDisplayname($arData[0]->getAttribute($this->config_users['displayname'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['mail'])[0]))
                        $user->setMail($arData[0]->getAttribute($this->config_users['mail'])[0]);
                    $user->setSn($arData[0]->getAttribute($this->config_users['name'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['tel'])[0]))
                        $user->setTel($arData[0]->getAttribute($this->config_users['tel'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['comp'])[0]))
                        $user->setComp($arData[0]->getAttribute($this->config_users['comp'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['aff'])[0]))
                        $user->setAff($arData[0]->getAttribute($this->config_users['aff'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['primaff'])[0]))
                        $user->setPrimAff($arData[0]->getAttribute($this->config_users['primaff'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['campus'])[0]))
                        $user->setCampus($arData[0]->getAttribute($this->config_users['campus'])[0]);
                    if (isset($arData[0]->getAttribute($this->config_users['site'])[0]))
                        $user->setSite($arData[0]->getAttribute($this->config_users['site'])[0]);
                    // Récupération du cn des groupes (memberof)
                    $tab = array();
                    $tab_members = $arData[0]->getAttribute($this->config_groups['memberof']);
                    $tab = array_splice($tab_members, 1);
                    $tab_cn = array();
                    foreach($tab as $dn) 
                        $tab_cn[] = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:_-]{1,}))(,ou=.*)/", "$3", strtolower($dn));
                    $user->setMemberof($tab_cn); 

                    $users[] = $user; 
                    
                    // Gestion des droits
                    $droits = 'Aucun';
                    // Droits DOSI seulement en visu
                    if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) {
                        $droits = 'Voir';
                    }
                    if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) || (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))) {
                        $droits = 'Modifier';
                    }
                    // Mise en session des résultats de la recherche
                    $request->getSession()->set('users', $users);
                    
                    if ($opt == 'addprivate') {
                        return $this->redirect($this->generateUrl('user_add_private', array('uid' => $user->getUid(), 'cn'=>$cn, 'opt'=>'recherche'))); 
                    }
                    if ($opt == 'add')
                    {
                        return $this->redirect($this->generateUrl('user_add', array('uid' => $user->getUid(), 'cn'=>$cn, 'liste' => $liste))); 
                    }
                    if ($opt == 'searchprivate')
                    {
                        return $this->redirect($this->generateUrl('see_user_private', array('uid' => $user->getUid())));
                    }
                        
                    return $this->redirect($this->generateUrl('user_update', array('uid' => $user->getUid())));              
                }
            }       
        }         
        return $this->render('User/usersearch.html.twig', array('form' => $form->createView(), 'opt' => $opt, 'cn' => $cn, 'liste' => $liste));
        
    }

    /**
    * Formulaire pour l'ajout d'utilisateurs en masse
    *
    * @Route("/multiple/{opt}/{cn}/{liste}",name="user_multiple")
    * @Template("AmuGroupieBundle:User:multiple.html.twig")
    */
    public function multipleAction(LdapFonctions $ldapfonctions, Request $request, $opt='search', $cn='', $liste='') {
        $this->init_config();
        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Vérification des droits
        $flag = "nok";
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
            // Recup des groupes dont l'utilisateur est admin
            $arDataAdminLogin = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
            for($i=0;$i<sizeof($arDataAdminLogin);$i++)
            {
                if ($cn==$arDataAdminLogin[$i]->getAttribute($this->config_groups['cn'])[0]) {
                    $flag = "ok";
                    break;
                }
            }
        }
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }


        $tab = array();
        // Création du formulaire
        $form = $this->createForm(UserMultipleType::class, $tab,
            array('action' => $this->generateUrl('user_multiple', array('opt'=>$opt, 'cn'=>$cn, 'liste'=>$liste)),
                'method' => 'GET'));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Initialisation des tableaux
            $tabErreurs = array();
            $tabUids = array();
            $users = array();
            $tabMemb = array();
                
            // Récuparation des données du formulaire
            $tab = $form->getData();
            $liste_ident = $tab['multiple'];
            // Récupérer un tableau avec une ligne par uid/mail
            $tabLignes = explode("\n", $liste_ident);
                
            // Log ajout sur le groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
                
            // Boucle sur la liste
            foreach($tabLignes as $l) {
                // on élimine les caractères superflus
                $ligne = trim($l);
                
                // test mail ou login
                if (stripos($ligne , "@")>0) {                   // C'est un mail
                    $r = $ldapfonctions->getUidFromMail($ligne, array($this->config_users['login'], $this->config_users['displayname'], $this->config_users['name'], $this->config_users['mail'], $this->config_users['tel'], $this->config_groups['memberof']));
                        
                    // Si pb connexion ldap
                    if ($r==false) {
                        // Affichage erreur
                        $this->get('session')->getFlashBag()->add('flash-error', "Erreur LDAP lors de la récupération du mail $ligne");          
                        // Log erreur
                        syslog(LOG_ERR, "LDAP ERREUR : get_uid_from_mail by $adm : $ligne");
                    }
                    else { 
                        // Si le mail n'est pas valide, on le note
                        if (sizeof($r) == 0)
                                $tabErreurs[] = $ligne;
                        else {
                            // Récupération des appartenances de l'utilisateur à ajouter
                            $tab_memb = $r[0]->getAttribute($this->config_groups['memberof']);
                            $arGroups = array_splice($tab_memb, 1);
                            $stop=0;
                            foreach($arGroups as $dn)  {
                                $c = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:_-]{1,}))(,ou=.*)/", "$3", strtolower($dn));
                                if ($c==$cn) {
                                    // l'utilisateur est déjà membre de ce groupe
                                    $stop = 1;
                                    break;
                                }
                            }
                                
                            // Si l'utilisateur n'est pas membre du groupe
                            if ($stop==0) {
                                // On ajoute l'uid à la liste des utilisateurs à ajouter
                                $tabUids[] = $r[0]->getAttribute('uid')[0];
                                
                                // Remplissage "user"
                                $user = new User();
                                $user->setUid($r[0]->getAttribute($this->config_users['login'])[0]);
                                $user->setDisplayname($r[0]->getAttribute($this->config_users['displayname'])[0]);
                                $user->setSn($r[0]->getAttribute($this->config_users['name'])[0]);
                                if (isset($r[0]->getAttribute($this->config_users['mail'])[0]))
                                    $user->setMail($r[0]->getAttribute($this->config_users['mail'])[0]);
                                if (isset($r[0]->getAttribute($this->config_users['tel'])[0]))
                                    $user->setTel($r[0]->getAttribute($this->config_users['tel'])[0]);
                                $users[] = $user;
                            }
                            else {
                                // L'utilisateur est déjà membre, on le note
                                $tabMemb[] = $r[0]->getAttribute($this->config_users['login'])[0];
                            }
                        }
                    }
                }
                else {
                    // C'est un login
                    $r = $ldapfonctions->TestUid($ligne, array($this->config_users['login'], $this->config_users['name'], $this->config_users['displayname'], $this->config_users['mail'], $this->config_users['tel'], $this->config_groups['memberof']));
                     
                    // Si pb connexion ldap
                    if ($r==false) {
                        // Affichage erreur
                        $this->get('session')->getFlashBag()->add('flash-error', "Erreur LDAP sur l'uid $ligne");
                                            
                        // Log erreur
                        syslog(LOG_ERR, "LDAP ERREUR : get_uid_from_mail by $adm : $ligne");
                    }
                    else {
                        // Si l'uid n'est pas valide, on le note
                        if (sizeof($r)==0) {
                            $tabErreurs[] = $ligne; 
                        }
                        else {
                            // Récupération des appartenances de l'utilisateur à ajouter
                            $tab_r = $r[0]->getAttribute($this->config_groups['memberof']);
                            $arGroups = array_splice($tab_r, 1);
                            $stop=0;
                            foreach($arGroups as $dn) {
                                $c = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:_-]{1,}))(,ou=.*)/", "$3", strtolower($dn));
                                if ($c==$cn) {
                                    // l'utilisateur est déjà membre de ce groupe
                                    $stop = 1;
                                    break;
                                }
                            }
                                
                            // Si l'utilisateur n'est pas membre du groupe
                            if ($stop==0) {
                                // On ajoute l'uid à la liste des utilisateurs à ajouter
                                $tabUids[] = $r[0]->getAttribute($this->config_users['login'])[0];
                                
                                // Remplissage "user"
                                $user = new User();
                                $user->setUid($r[0]->getAttribute($this->config_users['login'])[0]);
                                $user->setDisplayname($r[0]->getAttribute($this->config_users['displayname'])[0]);
                                $user->setSn($r[0]->getAttribute($this->config_users['name'])[0]);
                                if (isset($r[0]->getAttribute($this->config_users['mail'])[0]))
                                    $user->setMail($r[0]->getAttribute($this->config_users['mail'])[0]);
                                if (isset($r[0]->getAttribute($this->config_users['tel'])[0]))
                                    $user->setTel($r[0]->getAttribute($this->config_users['tel'])[0]);

                                $users[] = $user;
                            }
                            else {
                                // L'utilisateur est déjà membre, on le note
                                $tabMemb[] = $r[0]->getAttribute($this->config_users['login'])[0];
                            }
                        }
                    }
                }
            }
              
            // Ajout de la liste valide au groupe dans le LDAP
            $dn_group = $this->config_groups['cn']."=" . $cn . ", ".$this->config_groups['group_branch'].", ".$this->base;
            $b = $ldapfonctions->addMemberGroup($dn_group, $tabUids);
                
            if ($b) {
                // Log modif
                foreach($tabUids as $u) {
                    syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $u");
                }
            }
            else {
                // Log erreur
                syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $c, multiple user");
            }     
                
            // Affichage de ce qui a été fait dans le message flash
            if (sizeof($users)>0) {
                $this->get('session')->getFlashBag()->add('flash-notice', 'Les utilisateurs suivants ont été ajoutés : ');
                $l="";
                foreach($tabUids as $u)
                    $l = $l.', '.$u;
                $l = substr($l, 1) ;
                $this->get('session')->getFlashBag()->add('flash-notice', $l);
            }
            if (sizeof($tabErreurs)>0) {
                $this->get('session')->getFlashBag()->add('flash-notice', 'Les identifiants/mails suivants ne sont pas valides : ');
                $l="";
                foreach($tabErreurs as $u)
                    $l = $l.', '.$u;
                $l = substr($l, 1) ;
                $this->get('session')->getFlashBag()->add('flash-notice', $l);
            }
            if (sizeof($tabMemb)>0){
                $this->get('session')->getFlashBag()->add('flash-notice', 'Les utilisateurs avec les identifiants suivants sont déjà membres du groupe : ');
                $l="";
                foreach($tabMemb as $u)
                    $l = $l.', '.$u;
                $l = substr($l, 1) ;
                $this->get('session')->getFlashBag()->add('flash-notice', $l);
            }
                
            return $this->redirect($this->generateUrl('group_update', array('cn'=>$cn, 'liste'=>$liste)));
        }
                            
        return $this->render('User/multiple.html.twig', array('form' => $form->createView(), 'opt' => $opt, 'cn' => $cn, 'liste' => $liste));
    }
    
}
