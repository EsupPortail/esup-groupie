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
use App\Entity\Member;
use App\Entity\Membership;
use App\Form\GroupCreateType;
use App\Form\GroupModifType;
use App\Form\GroupSearchType;
use App\Form\GroupEditType;
use App\Form\UserEditType;
use App\Form\PrivateGroupCreateType;
use App\Form\PrivateGroupEditType;
use App\Service\LdapFonctions;

/**
 * group controller
 * @Route("/group")
 * 
 */
class GroupController extends AbstractController {

    protected $config_logs;
    protected $config_users;
    protected $config_groups;
    protected $config_private;
    protected $base;

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

/**********************************************************************************************************************************************************************************************************************************/
/* METHODES PUBLIQUES DU CONTROLLER                                                                                                                                                                                               */
/**********************************************************************************************************************************************************************************************************************************/

    /**
     * Affiche tous les groupes
     *
     * @Route("/all",name="all_groups")
     * @Template()
    */
    public function allgroupsAction(LdapFonctions $ldapfonctions) {

        $this->init_config();

        // Accès autorisé pour la DOSI
        $flag= "nok";
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            return $this->redirect($this->generateUrl('homepage'));
        }

        // Variables pour l'affichage "dossier" avec javascript
        $arEtages = array();
        $NbEtages = 0;
        $arEtagesPrec = array();
        $NbEtagesPrec = 0;

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Récupération des groupes dont l'utilisateur courant est administrateur (on ne récupère que les groupes publics)
        $arData = $ldapfonctions->recherche("(objectClass=".$this->config_groups['object_class'][0].")", array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);

        $groups = new ArrayCollection();
        for ($i=0; $i<sizeof($arData); $i++) {
            // on ne garde que les groupes publics
            if (!strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $groups[$i]->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
                if (isset($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0])) {
                    $groups[$i]->setAmugroupfilter($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0]);
                }
                else {
                    $groups[$i]->setAmugroupfilter("");
                }
                $groups[$i]->setAmugroupadmin("");

                // Mise en forme pour la présentation "dossier" avec javascript
                $separator = $this->config_groups['separator'];
                $arEtages = preg_split('/['.$separator.']+/', $arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $NbEtages = count($arEtages);
                $groups[$i]->setEtages($arEtages);
                $groups[$i]->setNbetages($NbEtages);
                $groups[$i]->setLastnbetages($NbEtagesPrec);

                // on marque la différence entre les dossiers d'affichage des groupes N et N-1
                $lastopen = 0;
                for ($j=0;$j<$NbEtagesPrec;$j++) {
                    if ($arEtages[$j]!=$arEtagesPrec[$j]) {
                        $lastopen = $j ;
                        $groups[$i]->setLastopen($lastopen);
                        break;
                    }
                }

                if (($NbEtagesPrec>=1) && ($lastopen == 0))
                    $groups[$i]->setLastopen($NbEtagesPrec-1);

                // on garde le nom du groupe précédent dans la liste
                $arEtagesPrec = $groups[$i]->getEtages();
                $NbEtagesPrec = $groups[$i]->getNbetages();
            }
        }

        return array('groups' => $groups);
    }

    /**
     * Affiche tous les groupes privés
     *
     * @Route("/all_private",name="all_private_groups")
     * @Template()
     */
    public function allprivateAction(LdapFonctions $ldapfonctions) {
        $this->init_config();

        // Accès autorisé pour la DOSI
        $flag= "nok";
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI'))
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
        // Récupération tous les groupes du LDAP
        $arData = $ldapfonctions->recherche("(objectClass=".$this->config_groups['object_class'][0].")", array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
         
        // Initialisation tableau des entités Group
        $groups = new ArrayCollection();
        for ($i=0; $i<sizeof($arData); $i++) {
            // on ne garde que les groupes privés
            if (strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $groups[$i]->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
            }
        }

        return array('groups' => $groups);
    }
 
    /**
     * Affiche tous les groupes dont l'utilisateur est administrateur
     *
     * @Route("/my_groups",name="my_groups")
     * @Template()
     */
    public function mygroupsAction(LdapFonctions $ldapfonctions) {
        $this->init_config();
        // Variables pour l'affichage "dossier" avec javascript 
        $arEtages = array();
        $NbEtages = 0;
        $arEtagesPrec = array();
        $NbEtagesPrec = 0;

        // Accès autorisé pour les gestionnaires
        $flag= "nok";
        if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE'))||(true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')))
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

        // Récupération des groupes dont l'utilisateur courant est administrateur (on ne récupère que les groupes publics)
        $arData = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base, array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);

        // Initialisation tableau des entités Group
        $groups = new ArrayCollection();
        for ($i=0; $i<sizeof($arData); $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
            $groups[$i]->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
            if (isset($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0])) {
                $groups[$i]->setAmugroupfilter($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0]);
            }
            else {
                $groups[$i]->setAmugroupfilter("");
            }
            $groups[$i]->setAmugroupadmin("");
            
            // Mise en forme pour la présentation "dossier" avec javascript
            $separator = $this->config_groups['separator'];
            $arEtages = preg_split('/['.$separator.']+/', $arData[$i]->getAttribute($this->config_groups['cn'])[0]);
            $NbEtages = count($arEtages);
            $groups[$i]->setEtages($arEtages);
            $groups[$i]->setNbetages($NbEtages);
            $groups[$i]->setLastnbetages($NbEtagesPrec);
                        
            // on marque la différence entre les dossiers d'affichage des groupes N et N-1
            $lastopen = 0;
            for ($j=0;$j<$NbEtagesPrec;$j++) {
                if ($arEtages[$j]!=$arEtagesPrec[$j]) {
                    $lastopen = $j ;
                    $groups[$i]->setLastopen($lastopen);
                    break;
                }
            }
            
            if (($NbEtagesPrec>=1) && ($lastopen == 0))
                $groups[$i]->setLastopen($NbEtagesPrec-1);
            
            // on garde le nom du groupe précédent dans la liste
            $arEtagesPrec = $groups[$i]->getEtages();
            $NbEtagesPrec = $groups[$i]->getNbetages();
        }
        
        return array('groups' => $groups);
    }
    
    /**
     * Affiche tous les groupes dont l'utilisateur est membre
     *
     * @Route("/memberships",name="memberships")
     * @Template()
     */
    public function membershipsAction(Request $request, LdapFonctions $ldapfonctions) {
        $this->init_config();
        // Variables pour l'affichage "dossier" avec javascript 
        $arEtages = array();
        $NbEtages = 0;
        $arEtagesPrec = array();
        $NbEtagesPrec = 0;

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Récupération des groupes dont l'utilisateur courant est administrateur (on ne récupère que les groupes publics)
        $arData = $ldapfonctions->recherche($this->config_groups['member']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base, array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        
        // Initialisation du tableau d'entités Group
        $groups = new ArrayCollection();
        for ($i=0; $i<sizeof($arData); $i++) {
            // on ne garde que les groupes publics
            if (!strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $groups[$i]->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
                if (isset($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0])) {
                    $groups[$i]->setAmugroupfilter($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0]);
                }
                else {
                    $groups[$i]->setAmugroupfilter("");
                }

                // Mise en forme pour la présentation "dossier" avec javascript
                $separator = $this->config_groups['separator'];
                $arEtages = preg_split('/['.$separator.']+/', $arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                $NbEtages = count($arEtages);
                $groups[$i]->setEtages($arEtages);
                $groups[$i]->setNbetages($NbEtages);
                $groups[$i]->setLastnbetages($NbEtagesPrec);

                // on marque la différence entre les dossiers d'affichage des groupes N et N-1
                $lastopen = 0;
                for ($j=0;$j<$NbEtagesPrec;$j++) {
                    if ($arEtages[$j]!=$arEtagesPrec[$j]) {
                        $lastopen = $j ;
                        $groups[$i]->setLastopen($lastopen);
                        break;
                    }
                }

                if (($NbEtagesPrec>=1) && ($lastopen == 0))
                    $groups[$i]->setLastopen($NbEtagesPrec-1);

                // on garde le nom du groupe précédent dans la liste
                $arEtagesPrec = $groups[$i]->getEtages();
                $NbEtagesPrec = $groups[$i]->getNbetages();
            }
        }
        
        return array('groups' => $groups);
    }
    
    /**
     * Affiche tous les groupes privés dont l'utilisateur est membre
     *
     * @Route("/private_memberships",name="private_memberships")
     * @Template()
     */
    public function privatemembershipsAction(Request $request, LdapFonctions $ldapfonctions) {
        $this->init_config();
        // Récupération des groupes privés dont l'utilisateur courant est membre
        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Recherche des groupes privés de l'utilisateur
        $result = $ldapfonctions->recherche($this->config_groups['member']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base, array($this->config_groups['cn'], $this->config_groups['desc']), 1, $this->config_groups['cn']);
        
        // Initialisation du tableau d'entités Group
        $groups = new ArrayCollection();
        $nb_groups=0;
        for ($i=0; $i<sizeof($result); $i++) {
            // on ne garde que les groupes privés
            if (strstr($result[$i]->getDn(), $this->config_private['private_branch'])) {
                $groups[$i] = new Group();
                $groups[$i]->setCn($result[$i]->getAttribute($this->config_groups['cn'])[0]);
                $groups[$i]->setDescription($result[$i]->getAttribute($this->config_groups['desc'])[0]);
                $nb_groups++;
            }
        }
        
        return array('groups' => $groups, 'nb_groups' => $nb_groups);
    }
        
    /**
     * Recherche de groupes
     *
     * @Route("/search/{opt}/{uid}",name="group_search")
     * @Template()
     */
    public function searchAction(Request $request, LdapFonctions $ldapfonctions,  $opt='search', $uid='') {
        $this->init_config();
        // Déclaration variables
        $groupsearch = new Group();
        $groups = array();

        // Création du formulaire de recherche de groupe
        $form = $this->createForm(GroupSearchType::class,
            $groupsearch,
            array('action' => $this->generateUrl('group_search', array('opt'=>$opt, 'uid'=>$uid)),
                  'method' => 'GET'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération des données du formulaire
            $groupsearch = $form->getData();

            // On déclare le LDAP
            try {
                $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
                $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
            }catch (ConnectionException $e) {
                throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
            }

            // On récupère le service ldapfonctions
            $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

            // Suivant l'option d'où on vient
            if (($opt=='search')||($opt=='mod')||($opt=='del')){
                // si on a sélectionné un proposition de la liste d'autocomplétion
                if ($groupsearch->getFlag() == '1') {
                    // On teste si on est sur le message "... Résultat partiel ..."
                    if ($groupsearch->getCn() == "... Résultat partiel ...") {
                        $this->get('session')->getFlashBag()->add('flash-notice', 'Le nom du groupe est invalide');
                        return $this->redirect($this->generateUrl('group_search', array('opt'=>$opt, 'uid'=>$uid)));
                    }
                    // Recherche exacte des groupes dans le LDAP
                    $arData=$ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=" . $groupsearch->getCn() . "))", array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
                }
                else {
                    // Recherche avec * des groupes dans le LDAP directement 
                    $arData=$ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=*" . $groupsearch->getCn() . "*))",array($this->config_groups['cn'],$this->config_groups['desc'],$this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
                }
                
                // si c'est un gestionnaire, on ne renvoie que les groupes dont il est admin
                $tab_cn_admin = array();
                if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
                    // Recup des groupes dont l'utilisateur est admin
                    $arDataAdmin = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
                    for($i=0;$i<sizeof($arDataAdmin);$i++)
                        $tab_cn_admin[$i] = $arDataAdmin[$i]->getAttribute($this->config_groups['cn'])[0];
                }

                // Compteur du nombre de résultats donnés par la recherche
                $nb = 0;
                for ($i=0; $i<sizeof($arData); $i++) {
                    // on ne garde que les groupes publics
                    if (!strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                        $groups[$i] = new Group();
                        $groups[$i]->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
                        $groups[$i]->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
                        if (isset($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0]))
                            $groups[$i]->setAmugroupfilter($arData[$i]->getAttribute($this->config_groups['groupfilter'])[0]);
                        else
                            $groups[$i]->setAmugroupfilter("");
                        $groups[$i]->setDroits('Aucun');

                        // Droits DOSI seulement en visu
                        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')) {
                            $groups[$i]->setDroits('Voir');
                        }

                        // Droits gestionnaire seulement sur les groupes dont il est admin
                        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
                            foreach ($tab_cn_admin as $cn_admin) {    
                                if ($cn_admin==$arData[$i]->getAttribute($this->config_groups['cn'])[0]) {
                                    $groups[$i]->setDroits('Modifier');
                                    break;
                                }
                            }
                        }

                        // Droits Admin
                        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                            $groups[$i]->setDroits('Modifier');
                        }
                        $nb++;
                    }
                }
            
                // Mise en session des résultats de la recherche
                $this->get('session')->set('groups', $groups);

                // Si on a un seul résultat de recherche, affichage direct du groupe concerné en fonction des droits
                if ($opt=='search') {
                    if ($nb==1) {
                        if ($groups[0]->getDroits()=='Modifier') {
                            return $this->redirect($this->generateUrl('group_update', array('cn'=>$groups[0]->getCn(), 'liste' => 'recherchegroupe')));
                        }

                        if ($groups[0]->getDroits()=='Voir') {
                           return $this->redirect($this->generateUrl('see_group', array('cn'=>$groups[0]->getCn(), 'mail' => true, 'liste' => 'recherchegroupe')));
                        }
                    }
                } elseif ($opt=='mod') {
                    if ($nb==1) {
                        if ($groups[0]->getDroits() == 'Modifier') {
                            if ($groups[0]->getAmuGroupFilter() == "") {
                                return $this->redirect($this->generateUrl('group_modify', array('cn' => $groups[0]->getCn(), 'desc' => $groups[0]->getDescription(), 'filt' => 'no')));
                            } else {
                                return $this->redirect($this->generateUrl('group_modify', array('cn' => $groups[0]->getCn(), 'desc' => $groups[0]->getDescription(), 'filt' => $groups[0]->getAmuGroupFilter())));
                            }
                        }
                    }
                }
  
                return $this->render('Group/searchres.html.twig',array('groups' => $groups, 'opt' => $opt, 'uid' => $uid));
            }
            else {
                if ($opt=='add') {
                    // Renvoi vers le fonction group_add
                    return $this->redirect($this->generateUrl('group_add', array('cn_search'=>$groupsearch->getCn(), 'uid'=>$uid, 'flag_cn'=> $groupsearch->getFlag())));
                }
            }
        }
        
        return $this->render('Group/search.html.twig', array('form' => $form->createView(), 'opt' => $opt, 'uid' => $uid));
        
    }
    
    /**
     * Recherche de groupes pour la suppression
     *
     * @Route("/searchdel",name="group_search_del")
     * @Template()
     */
    public function searchdelAction(Request $request) {
        return $this->redirect($this->generateUrl('group_search', array('opt' => 'del', 'uid'=>'')));
    }
    
    /**
     * Recherche de groupes pour la modification
     *
     * @Route("/searchmod",name="group_search_modify")
     * @Template()
     */
    public function searchmodAction(Request $request) {
        return $this->redirect($this->generateUrl('group_search', array('opt' => 'mod', 'uid'=>'')));
    }

    /**
     * Ajout de personnes dans un groupe
     *
     * @Route("/add/{cn_search}/{uid}/{flag_cn}",name="group_add")
     * @Template("Group/searchadd.html.twig")
     */
    public function addAction(Request $request, LdapFonctions $ldapfonctions, $cn_search='', $uid='', $flag_cn=0) {
        $this->init_config();

        // Accès autorisé pour les gestionnaires et les admins
        $flag= "nok";
        if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) || (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')))
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
            $tab_cn_admin_login = array();
            // Recup des groupes dont l'utilisateur courant (logué) est admin
            $arDataAdminLogin = $ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$this->container->get('security.token_storage')->getToken()->getAttribute("uid").",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
            for($i=0;$i<sizeof($arDataAdminLogin);$i++)
                $tab_cn_admin_login[$i] = $arDataAdminLogin[$i]->getAttribute($this->config_groups['cn'])[0];
        }

        // Récupération utilisateur et début d'initialisation de l'objet
        $user = new User();
        $user->setUid($uid);
        // Récup infos utilisateur dans le LDAP
        $arDataUser=$ldapfonctions->recherche($this->config_users['login']."=".$uid, array($this->config_users['displayname'], $this->config_groups['memberof']), 0, $this->config_users['login']);
        $user->setDisplayname($arDataUser[0]->getAttribute($this->config_users['displayname'])[0]);
        
        // Utilisateur initial pour détecter les modifications
        $userini = new User();
        $userini->setUid($uid);
        $userini->setDisplayname($arDataUser[0]->getAttribute($this->config_users['displayname'])[0]);
        
        // Mise en forme du tableau contenant les cn des groupes dont l'utilisateur recherché est membre
        $tab_memberof = $arDataUser[0]->getAttribute($this->config_groups['memberof']);
        $tab = array_splice($tab_memberof, 1);
        $tab_cn = array();
        foreach($tab as $dn) {
            $tab_cn[] = preg_replace("/(".$this->config_groups['cn']."=)(([A-Za-z0-9:._-]{1,}))(,".$this->config_groups['group_branch'].".*)/", "$3", strtolower($dn));
        }

        // Récupération des groupes dont l'utilisateur recherché est admin
        $arDataAdmin=$ldapfonctions->recherche($this->config_groups['groupadmin']."=".$this->config_users['login']."=".$uid.",".$this->config_users['people_branch'].",".$this->base,array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        $tab_cn_admin = array();
        for($i=0;$i<sizeof($arDataAdmin);$i++) {
            $tab_cn_admin[$i] = $arDataAdmin[$i]->getAttribute($this->config_groups['cn'])[0];
        }

        // Si on a sélectionné une proposition dans la liste d'autocomplétion
        if ($flag_cn=='1') {
            // On teste si on est sur le message "... Résultat partiel ..."
            if ($cn_search == "... Résultat partiel ...") {
                $this->get('session')->getFlashBag()->add('flash-notice', 'Le nom du groupe est invalide');                        
                return $this->redirect($this->generateUrl('group_search', array('opt'=>'add', 'uid'=>$uid, 'cn'=> $cn_search)));
            }
            
            // Recherche exacte sur le cn sélectionné dans le LDAP
            $arData=$ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=" . $cn_search . "))",array($this->config_groups['cn'],$this->config_groups['desc'],$this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        }
        else {
            // Recherche avec * dans le LDAP
            $arData=$ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=*" . $cn_search . "*))",array($this->config_groups['cn'],$this->config_groups['desc'],$this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        }

        // Récupération des groupes publics issus de la recherche
        $cpt=0;
        for ($i=0; $i<sizeof($arData); $i++) {
            // on ne garde que les groupes publics
            if (!strstr($arData[$i]->getDn(), $this->config_private['private_branch'])) {
                $tab_cn_search[$cpt] = $arData[$i]->getAttribute($this->config_groups['cn'])[0];
                $cpt++;
            }
        }
                           
        // on remplit l'objet user avec les groupes retournés par la recherche LDAP
        $memberships = new ArrayCollection();
        // Idem pour l'objet userini
        $membershipsini = new ArrayCollection();
        foreach($tab_cn_search as $groupname)
        {
            $membership = new Membership();
            $membership->setGroupname($groupname);
            $membership->setDroits('Aucun');
            $membershipini = new Membership();
            $membershipini->setGroupname($groupname);
            $membershipini->setDroits('Aucun');
            // Remplissage des droits "membre"
            foreach($tab_cn as $cn) {
                if ($cn==$groupname) {
                    $membership->setMemberof(TRUE);
                    $membershipini->setMemberof(TRUE);
                    break;
                }
                else {
                    $membership->setMemberof(FALSE);
                    $membershipini->setMemberof(FALSE);
                 }
            }
            
            //Remplissage des droits admin
            foreach($tab_cn_admin as $cn) {
                if ($cn==$groupname) {
                    $membership->setAdminof(TRUE);
                    $membershipini->setAdminof(TRUE);
                    break;
                }
                else {
                    $membership->setAdminof(FALSE);
                    $membershipini->setAdminof(FALSE);
                 }
            }
                        
            // Gestion droits pour un gestionnaire
            if (true === $this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
                foreach($tab_cn_admin_login as $cn) {
                    if ($cn==$groupname) {
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
        $user->setMemberships($memberships);      
        $userini->setMemberships($membershipsini);
        
        // Formulaire
        $editForm = $this->createForm(UserEditType::class, $user, array(
            'action' => $this->generateUrl('group_add', array('cn_search'=> $cn_search, 'uid' => $uid, 'flag_cn' => $flag_cn)),
            'method' => 'POST',
        ));
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            // Initialisation des entités
            $userupdate = new User();
            $m_update = new ArrayCollection();      
            
            // Récupération des données du formulaire
            $userupdate = $editForm->getData();
            $m_update = $userupdate->getMemberships();
            
            // Log Mise à jour des membres du groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
            
            // Pour chaque appartenance
            for ($i=0; $i<sizeof($m_update); $i++) {
                $memb = $m_update[$i];
                $dn_group = $this->config_groups['cn']."=" . $memb->getGroupname() . ", ".$this->config_groups['group_branch'].", ".$this->base;
                $gr = $memb->getGroupname();
                
                // Traitement des membres  
                // Si il y a changement pour le membre, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getMemberof() != $membershipsini[$i]->getMemberof()) {
                    if ($memb->getMemberof()) {
                        // Ajout utilisateur dans groupe
                        $r = $ldapfonctions->addMemberGroup($dn_group, array($uid));
                        // Log des modifications
                        if ($r==true) 
                            syslog(LOG_INFO, "add_member by $adm : group : $gr, user : $uid ");
                        else 
                            syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $gr, user : $uid");
                    }
                    else {
                        // Suppression utilisateur du groupe
                        $r = $ldapfonctions->delMemberGroup($dn_group, array($uid));
                        if ($r)
                            syslog(LOG_INFO, "del_member by $adm : group : $gr, user : $uid ");
                        else 
                            syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $gr, user : $uid");
                    }
                }
                // Traitement des admins
                // Si il y a changement pour admin, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getAdminof() != $membershipsini[$i]->getAdminof()) {
                    if ($memb->getAdminof()) {
                        // Ajout admin dans le groupe
                        $r = $ldapfonctions->addAdminGroup($dn_group, array($uid));
                        if ($r)
                            syslog(LOG_INFO, "add_admin by $adm : group : $gr, user : $uid ");
                        else 
                            syslog(LOG_ERR, "LDAP ERROR : add_admin by $adm : group : $gr, user : $uid ");
                    }
                    else {
                        // Suppression admin du groupe
                        $r = $ldapfonctions->delAdminGroup($dn_group, array($uid));
                        if ($r)
                            syslog(LOG_INFO, "del_admin by $adm : group : $gr, user : $uid ");
                        else
                            syslog(LOG_ERR, "LDAP ERROR : del_admin by $adm : group : $gr, user : $uid ");
                    }
                }
            }
            // Ferme fichier de log
            closelog();
            // Notification message flash
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');
            
            // Retour à la page update d'un utilisateur
            return $this->redirect($this->generateUrl('user_update', array('uid'=>$uid)));
        }
         
        // Affichage via le fichier twig
        return array(
            'user'      => $user,
            'cn_search' => $cn_search,
            'flag_cn' => $flag_cn,
            'form'   => $editForm->createView(),
        );
    }
    
    /**
     * Voir les membres et administrateurs d'un groupe.
     *
     * @Route("/see/{cn}/{mail}/{liste}", name="see_group")
     * @Template()
     */
    public function seeAction(Request $request, LdapFonctions $ldapfonctions, $cn, $mail, $liste)
    {
        $this->init_config();
        // Initialisation des tableaux d'entités
        $users = array();
        $admins = array();

        // Vérification des droits
        $flag = "nok";
        // Dans le cas d'un membre
        if ((true === $this->get('security.authorization_checker')->isGranted('ROLE_MEMBRE'))||(true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI')))
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

        // Récupération du groupe recherché
        $result = $ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=" . $cn . "))", array($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter']), 1, $this->config_groups['cn']);
        if (isset($result[0]->getAttribute($this->config_groups['groupfilter'])[0]))
            $amugroupfilter = $result[0]->getAttribute($this->config_groups['groupfilter'])[0];
        else
            $amugroupfilter = "";
        
        // Recherche des membres dans le LDAP
        //$arUsers = $this->getLdap()->getMembersGroup($cn);
        $arUsers = $ldapfonctions->getMembersGroup($cn);
        
        // on remplit le tableau d'entités
        for ($i=0; $i<sizeof($arUsers); $i++) {
            $users[$i] = new User();
            $users[$i]->setUid($arUsers[$i]->getAttribute($this->config_users['login'])[0]);
            $users[$i]->setSn($arUsers[$i]->getAttribute($this->config_users['name'])[0]);
            $users[$i]->setDisplayname($arUsers[$i]->getAttribute($this->config_users['displayname'])[0]);
            if ($mail=='true')
                $users[$i]->setMail($arUsers[$i]->getAttribute($this->config_users['mail'])[0]);
            if (isset($arUsers[$i]->getAttribute($this->config_users['tel'])[0]))
                $users[$i]->setTel($arUsers[$i]->getAttribute($this->config_users['tel'])[0]);
            else 
                $users[$i]->setTel("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['primaff'])[0]))
                $users[$i]->setPrimAff($arUsers[$i]->getAttribute($this->config_users['primaff'])[0]);
            else
                $users[$i]->setPrimAff("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['aff'])[0]))
                $users[$i]->setAff($arUsers[$i]->getAttribute($this->config_users['aff'])[0]);
            else
                $users[$i]->setAff("");

        }
        
        // Recherche des administrateurs du groupe
        $arAdmins = $ldapfonctions->getAdminsGroup($cn);
        
        if (sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])) > 0) {
            $nb_admins = sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin']));
            // on remplit le tableau d'entités
            for ($i=0; $i<sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])); $i++) {
                $uid = preg_replace("/(".$this->config_users['login']."=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", strtolower($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])[$i]));
                $result = $ldapfonctions->getInfosUser($uid);
                $admins[$i] = new User();
                $admins[$i]->setUid($result[0]->getAttribute($this->config_users['login'])[0]);
                $admins[$i]->setSn($result[0]->getAttribute($this->config_users['name'])[0]);
                $admins[$i]->setDisplayname($result[0]->getattribute($this->config_users['displayname'])[0]);
                if (isset($result[0]->getAttribute($this->config_users['mail'])[0]))
                    $admins[$i]->setMail($result[0]->getAttribute($this->config_users['mail'])[0]);
                else
                    $admins[$i]->setMail("");
                if (isset($result[0]->getAttribute($this->config_users['tel'])[0]))
                    $admins[$i]->setTel($result[0]->getAttribute($this->config_users['tel'])[0]);
                else
                    $admins[$i]->setTel("");
                if (isset($result[0]->getAttribute($this->config_users['aff'])[0]))
                    $admins[$i]->setAff($result[0]->getattribute($this->config_users['aff'])[0]);
                else
                    $admins[$i]->setAff("");
                if (isset($result[0]->getAttribute($this->config_users['primaff'])[0]))
                    $admins[$i]->setPrimAff($result[0]->getAttribute($this->config_users['primaff'])[0]);
                else
                    $admins[$i]->setPrimAff("");

            }
        }
        else {
            $nb_admins=0;
        }

        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_DOSI'))
            $dosi=1;
        else
            $dosi=0;
        
        // Affichage via le fichier twig
        return array('cn' => $cn,
                    'amugroupfilter' => $amugroupfilter,
                    'nb_membres' => sizeof($arUsers),
                    'users' => $users,
                    'nb_admins' => $nb_admins,
                    'admins' => $admins,
                    'dosi' => $dosi,
                    'mail' => $mail,
                    'liste' => $liste);
    }
    
     /**
     * Voir les membres et administrateurs d'un groupe privé.
     *
     * @Route("/see_private/{cn}/{opt}", name="see_private_group")
     * @Template()
     */
    public function seeprivateAction(Request $request, LdapFonctions $ldapfonctions, $cn, $opt)
    {
        $this->init_config();
        $users = array();
        // Vérification des droits
        $flag = "nok";
        // Dans le cas d'un membre
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

        // Récupération du propriétaire du groupe
        $cn_perso = substr($cn, strlen($this->config_private['prefix'])+1); // on retire le préfixe amu:perso:
        $uid_prop = strstr($cn_perso, ":", TRUE);
        $result = $ldapfonctions->getInfosUser($uid_prop);
        $proprietaire = new User();
        $proprietaire->setUid($result[0]->getAttribute("uid")[0]);
        $proprietaire->setSn($result[0]->getAttribute($this->config_users['name'])[0]);
        $proprietaire->setDisplayname($result[0]->getAttribute($this->config_users['displayname'])[0]);
        $proprietaire->setMail($result[0]->getAttribute($this->config_users['mail'])[0]);
        $proprietaire->setTel($result[0]->getAttribute($this->config_users['tel'])[0]);
        
        // Recherche des membres dans le LDAP
        $arUsers = $ldapfonctions->getMembersGroup($cn.",".$this->config_private['private_branch']);
        $nb_users = sizeof($arUsers);
                 
        for ($i=0; $i<sizeof($arUsers); $i++) {
            $users[$i] = new User();
            $users[$i]->setUid($arUsers[$i]->getAttribute($this->config_users['login'])[0]);
            $users[$i]->setSn($arUsers[$i]->getAttribute($this->config_users['name'])[0]);
            $users[$i]->setDisplayname($arUsers[$i]->getAttribute($this->config_users['displayname'])[0]);
            $users[$i]->setMail($arUsers[$i]->getAttribute($this->config_users['mail'])[0]);
            $users[$i]->setTel($arUsers[$i]->getAttribute($this->config_users['tel'])[0]);
        }
        
        // Affichage via twig
        return array('cn' => $cn,
                    'nb_membres' => $nb_users,
                    'proprietaire' => $proprietaire,
                    'users' => $users,
                    'opt' => $opt);
    }
        
    /**
     * Création d'un groupe
     *
     * @Route("/create",name="group_create")
     * @Template("Group/group.html.twig")
     */
    public function createAction(Request $request, LdapFonctions $ldapfonctions) {
        $this->init_config();
        // Initialisation des entités
        $group = new Group();
        $groups = array();

        // Vérification des droits
        $flag = "nok";
        // Droits seulement pour les admins de l'appli
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }
        
        // Création du formulaire de création de groupe
        $form = $this->createForm(GroupCreateType::class,
            new Group(),
            array('action' => $this->generateUrl('group_create'),
                'method' => 'GET'));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération des données
            $group = $form->getData();

            // On déclare le LDAP
            try {
                $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
                $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
            }catch (ConnectionException $e) {
                throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
            }

            // On récupère le service ldapfonctions
            $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

            if ($group->getAmugroupfilter() != "") {
                // Test validité du filtre si c'est un filtre LDAP
                $filtre = $group->getAmugroupfilter();
                $b = $ldapfonctions->testAmugroupfilter($filtre);
                if ($b === true) {
                    // Le filtre LDAP est valide, on continue
                } else {
                    // affichage erreur filtre invalide
                    $this->get('session')->getFlashBag()->add('flash-error', 'amuGroupFilter n\'est pas valide !');

                    // Retour à la page contenant le formulaire de création de groupe
                    return $this->render('Group/group.html.twig', array('form' => $form->createView()));
                }

            }
            
            // Log création de groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
                
            // Création du groupe dans le LDAP
            $infogroup = $group->infosGroupeLdap($this->config_groups['cn'], $this->config_groups['desc'], $this->config_groups['groupfilter'], $this->config_groups['object_class']);
            $b =$ldapfonctions->createGroupeLdap($this->config_groups['cn']."=".$group->getCn().",".$this->config_groups['group_branch'].",".getenv("base_dn") , $infogroup);
            if ($b==true) {          
                // affichage groupe créé
                $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été créé');
                $groups[0] = $group;
                $cn = $group->getCn();
                
                // Log création OK
                syslog(LOG_INFO, "create_group by $adm : group : $cn");
               
                // Affichage via fichier twig
                return $this->render('Group/create.html.twig',array('groups' => $groups));
            }
            else {
                // affichage erreur
                $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la création du groupe');
                $groups[0] = $group;
                $cn = $group->getCn();
                
                // Log erreur
                syslog(LOG_ERR, "LDAP ERREUR : create_group by $adm : group : $cn");
                
                // Retour à la page contenant le formulaire de création de groupe
                return $this->render('Group/group.html.twig', array('form' => $form->createView()));
            }
            
            // Ferme le fichier de log
            closelog();
        }
        
        // Affichage formulaire de création de groupe
        return $this->render('Group/group.html.twig', array('form' => $form->createView()));
    }
    
    /**
     * Création d'un groupe privé
     *
     * @Route("/private/create/{nb_groups}",name="private_group_create")
     * @Template("Group/createprivate.html.twig")
     */
    public function createPrivateAction(Request $request, LdapFonctions $ldapfonctions, $nb_groups) {
        $this->init_config();

        // Vérification des droits
        $flag = "nok";
        // Dans le cas d'un membre
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_MEMBRE'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        // Limite sur le nombre de groupes privés qu'il est possible de créer
        if ($nb_groups>20){
            return $this->render('Group/limite.html.twig');
        }
        
        // Initialisation des entités
        $group = new Group();
        $groups = array();
                
        // Création du formulaire
        $form = $this->createForm(PrivateGroupCreateType::class,
            $group,
            array('action' => $this->generateUrl('private_group_create', array('nb_groups'=>$nb_groups)),
                'method' => 'GET'));

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération de l'entrée utilisateur
            $group = $form->getData();
            
            // Vérification de la validité du champ cn : pas d'espaces, accents, caractères spéciaux
            $test = preg_match("#^[A-Za-z0-9-_]+$#i", $group->getCn());
            if ($test>0) {
                // le nom du groupe est valide, on peut le créer
                // Log création de groupe
                openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
                $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");

                // On déclare le LDAP
                try {
                    $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
                    $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
                }catch (ConnectionException $e) {
                    throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
                }

                // On récupère le service ldapfonctions
                $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

                // Création du groupe dans le LDAP
                $infogroup = $group->infosGroupePriveLdap($adm);
                $b = $ldapfonctions->createGroupeLdap($infogroup['dn'], $infogroup['infos']);
                if ($b==true) { 
                    //Le groupe a bien été créé
                    $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été créé');
                    $groups[0] = $group;
                    $cn = $this->config_private['prefix'].":".$adm.":".$group->getCn();
                    $group->setCn($cn);

                    // Log création OK
                    syslog(LOG_INFO, "create_private_group by $adm : group : $cn");

                    // Ajout du propriétaire dans le groupe
                    $r = $ldapfonctions->addMemberGroup($infogroup['dn'], array($adm));
                    if ($r) {
                        // Log modif
                        syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $adm");
                    }
                    else {
                        syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $adm");
                    }
                    // Ferme fichier log
                    closelog();

                    // Retour à la page update d'un groupe
                    return $this->redirect($this->generateUrl('private_group_update', array('cn'=>$cn)));
                    // Affichage création OK
                    return $this->render('Group/privatecreation.html.twig',array('groups' => $groups));
                }
                else {
                    // affichage erreur
                    $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la création du groupe');
                    $groups[0] = $group;
                    $cn = $this->config_private['prefix'].":".$adm.":".$group->getCn();

                    // Log erreur
                    syslog(LOG_ERR, "LDAP ERREUR : create_private_group by $adm : group : $cn");

                    // Affichage page 
                    return $this->render('Group/createprivate.html.twig', array('form' => $form->createView(), 'nb_groups' => $nb_groups));
                }

                // Ferme le fichier de log
                closelog();
            }
            else {
                // le nom du groupe n'est pas valide, notification à l'utilisateur
                // affichage erreur
                $this->get('session')->getFlashBag()->add('flash-error', 'Le nom du groupe est invalide. Merci de supprimer les accents et caractères spéciaux.');
                    
                // Affichage page du formulaire
                return $this->render('Group/createprivate.html.twig', array('form' => $form->createView(), 'nb_groups' => $nb_groups));
            }
        }
        return $this->render('Group/createprivate.html.twig', array('form' => $form->createView(), 'nb_groups' => $nb_groups));
    }
    
     /**
     * Supprimer un groupe.
     *
     * @Route("/delete/{cn}", name="group_delete")
     * @Template()
     */
    public function deleteAction(Request $request, LdapFonctions $ldapfonctions, $cn)
    {
        $this->init_config();
        // Log suppression de groupe
        openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
        $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
        
        //Suppression du groupe dans le LDAP
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
        // Suppression autorisée pour les admin de l'appli seulement
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            $flag = "ok";
        if ($flag=="nok") {
            // Retour à l'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opération');
            return $this->redirect($this->generateUrl('homepage'));
        }

        $b = $ldapfonctions->deleteGroupeLdap($cn);
        if ($b==true) {
            //Le groupe a bien été supprimé
            $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été supprimé');
            
            // Log
            syslog(LOG_INFO, "delete_group by $adm : group : $cn");
            
            return $this->render('Group/delete.html.twig',array('cn' => $cn));
        }
        else {
            // Log erreur
            syslog(LOG_ERR, "LDAP ERROR : delete_group by $adm : group : $cn");
            // affichage erreur
            $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la suppression du groupe');
            // Retour page de recherche
            return  $this->redirect($this->generateUrl('group_search_del'));
        }
        
        // Ferme fichier de log
        closelog();
    }
    
    /**
     * Choisir un groupe privé à supprimer
     *
     * @Route("/private/delete",name="private_group_delete")
     * @Template("Group/deleteprivate.html.twig")
     */
    public function deletePrivateAction(Request $request, LdapFonctions $ldapfonctions) {
        $this->init_config();
        $uid = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
        // Recherche des groupes dans le LDAP
        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        $arData = $ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=".$this->config_private['prefix'].":".$uid.":*))",array($this->config_groups['cn'],$this->config_groups['desc']), 1, $this->config_groups['cn']);
    
        $groups = new ArrayCollection();
        for ($i=0; $i<sizeof($arData); $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($arData[$i]->getAttribute($this->config_groups['cn'])[0]);
            $groups[$i]->setDescription($arData[$i]->getAttribute($this->config_groups['desc'])[0]);
            
        }
        
        return array('groups' => $groups);
    }
    
    /**
     * Supprimer un groupe privé.
     *
     * @Route("/private/del_1/{cn}", name="private_group_del_1")
     * @Template()
     */
    public function del1PrivateAction(Request $request,LdapFonctions $ldapfonctions, $cn) {
        $this->init_config();
        // Log suppression de groupe
        openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
        $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
        
        // Suppression du groupe dans le LDAP
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
        // Dans le cas d'un gestionnaire
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_MEMBRE')) {
            // Recup des groupes privés de l'utilisateur
            $result = $ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=".$this->config_private['prefix'].":".$adm.":*))", array($this->config_groups['cn'], $this->config_groups['desc']), 1, $this->config_groups['cn']);
            for($i=0;$i<sizeof($result);$i++) {
                if ($cn==$result[$i]->getAttribute($this->config_groups['cn'])[0]) {
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

        $b = $ldapfonctions->deleteGroupeLdap($cn.",".$this->config_private['private_branch']);
        if ($b==true) {
            //Le groupe a bien été supprimé
            $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été supprimé');
            
            // Log
            syslog(LOG_INFO, "delete_private_group by $adm : group : $cn");                        
        }
        else {
            // Log erreur
            syslog(LOG_ERR, "LDAP ERROR : delete_private_group by $adm : group : $cn");
            // affichage erreur
            $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la suppression du groupe');
        }
        
        // Ferme fichier de log
        closelog();

        // Retour page de gestion des groupes privés
        return  $this->redirect($this->generateUrl('private_group'));

    }
    
    /**
     * Modifier un groupe.
     *
     * @Route("/modify/{cn}/{desc}/{filt}", name="group_modify")
     * @Template()
     */
    public function modifyAction(Request $request, LdapFonctions $ldapfonctions, $cn, $desc, $filt)
    {
        $this->init_config();
        $group = new Group();
        $groups = array();

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);
        
        $dn = $this->config_groups['cn']."=".$cn.", ".$this->config_groups['group_branch'].", ".$this->base;

        // Pré-remplir le formulaire avec les valeurs actuelles du groupe
        $group->setCn($cn);
        $group->setDescription($desc);
        if ($filt=="no")
            $group->setAmugroupfilter("");
        else
            $group->setAmugroupfilter($filt);
        
        $form = $this->createForm(GroupModifType::class, $group);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupmod = new Group();
            $groupmod = $form->getData();

            // Log modif de groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");

            // Cas particulier de la suppression amugroupfilter
            if (($filt != "no") && ($groupmod->getAmugroupfilter() == "")) {
                // Suppression de l'attribut
                $b = $ldapfonctions->delAmuGroupFilter($dn, $filt);
                if ($b == true) {
                    //Le filtre du groupe a bien été supprimé
                    $this->get('session')->getFlashBag()->add('flash-notice', 'AmuGroupFilter  a bien été supprimé');
                    // Log
                    syslog(LOG_INFO, "delete_amugroupfilter by $adm : group : $cn");
                } else {
                    // Log erreur
                    syslog(LOG_ERR, "LDAP ERROR : delete_amugroupfilter by $adm : group : $cn");
                    // affichage erreur
                    $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la suppression de l\'attribut amuGroupFilter');
                    return $this->render('Group/modifyform.html.twig', array('form' => $form->createView(), 'group' => $group));
                }
            }

            // Modification du groupe dans le LDAP
            $b = $ldapfonctions->modGroup($dn, $groupmod->getDescription(), $groupmod->getAmugroupfilter());
            if ($b === true) {
                //Le groupe a bien été modifié
                // Log modif de groupe OK
                syslog(LOG_INFO, "modif_group by $adm : group : $cn");

                // affichage groupe créé
                $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été modifié');
                $groups[0] = $group;
            } else {
                if ($b == 2) {
                    // Erreur filtre
                    $this->get('session')->getFlashBag()->add('flash-error', 'amuGroupFilter n\'est pas valide !');
                } else {
                    // Log Erreur LDAP
                    syslog(LOG_ERR, "LDAP ERROR : modif_group by $adm : group : $cn");
                    $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors de la modification du groupe');
                }
                return $this->render('Group/modifyform.html.twig', array('form' => $form->createView(), 'group' => $group));
            }

            // Renommage groupe dans le LDAP
            if ($cn != $groupmod->getCn()) {
                $b = $ldapfonctions->renameGroupeLdap($dn, $groupmod->getCn());
                if ($b == true) {
                    //Le groupe a bien été renommé
                    // Log modif de groupe OK
                    $new_cn = $groupmod->getCn();
                    syslog(LOG_INFO, "rename_group by $adm : group : $cn, new : $new_cn");

                    // affichage groupe modifé
                    $this->get('session')->getFlashBag()->add('flash-notice', 'Le groupe a bien été renommé');
                    $groups[0] = $group;
                    return $this->render('Group/modifygroup.html.twig', array('groups' => $groups));
                } else {
                    // Log Erreur LDAP
                    syslog(LOG_ERR, "LDAP ERROR : modif_group by $adm : group : $cn");
                    $this->get('session')->getFlashBag()->add('flash-error', 'Erreur LDAP lors du renommage du groupe');
                }
            }

            // retour sur la fiche du groupe
            return $this->redirect($this->generateUrl('group_update', array('cn'=>$cn, 'liste' => 'recherchegroupe')));
            
            // Ferme fichier log
            closelog();
        }
        return $this->render('Group/modifyform.html.twig', array('form' => $form->createView(), 'group' => $group));
    }

    /**
    * Affichage d'une liste de groupe en session
    *
    * @Route("/afficheliste/{opt}/{uid}",name="group_display")
    */
    public function displayAction(Request $request, $opt='search', $uid='') {
        $this->init_config();
        // Récupération des groupes mis en session
        //$groups = $this->container->get('request')->getSession()->get('groups');
        $groups = $this->get('session')->get('groups');

        return $this->render('Group/search.html.twig',array('groups' => $groups, 'opt' => $opt, 'uid' => $uid));
    }
    
    /**
    * Gestion des groupes privés de l'utilisateur
    *
    * @Route("/private",name="private_group")
    * @Template() 
    */
    public function privateAction(Request $request, LdapFonctions $ldapfonctions) {
        $this->init_config();
        // Récupération uid de l'utilisateur logué
        $uid = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
// On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        // Recherche des groupes dans le LDAP
        $result = $ldapfonctions->recherche("(&(objectClass=".$this->config_groups['object_class'][0].")(".$this->config_groups['cn']."=".$this->config_private['prefix'].":".$uid.":*))", array($this->config_groups['cn'], $this->config_groups['desc']), 1, $this->config_groups['cn']);
    
        $groups = new ArrayCollection();
        for ($i=0; $i<sizeof($result); $i++) {
            $groups[$i] = new Group();
            $groups[$i]->setCn($result[$i]->getattribute($this->config_groups['cn'])[0]);
            $groups[$i]->setDescription($result[$i]->getAttribute($this->config_groups['desc'])[0]);
        }
        // Affichage du tableau de groupes privés via fichier twig
        return array('groups' => $groups, 'nb_groups' => sizeof($result));
    }
    
    /**
     * Mettre à jour les membres d'un groupe privé.
     *
     * @Route("/private/update/{cn}", name="private_group_update")
     * @Template("Group/privateupdate.html.twig")
     */
    public function privateupdateAction(Request $request, LdapFonctions $ldapfonctions, $cn)
    {
        $this->init_config();
        // Initialisation des entités
        $group = new Group();
        $group->setCn($cn);
        $members = new ArrayCollection();
        
        // Groupe initial pour détecter les modifications
        $groupini = new Group();
        $groupini->setCn($cn);
        $membersini = new ArrayCollection();

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        $flag = "nok";
        // Dans le cas d'un utilisateur
        if (true === $this->get('security.authorization_checker')->isGranted('ROLE_MEMBRE')) {
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

        // Recherche des membres dans le LDAP
        $arUsers = $ldapfonctions->getMembersGroup($cn.",".$this->config_private['private_branch']);
        
        // Affichage des membres  
        for ($i=0; $i<sizeof($arUsers); $i++) {
            $members[$i] = new Member();
            $members[$i]->setUid($arUsers[$i]->getAttribute($this->config_users['login'])[0]);
            $members[$i]->setDisplayname($arUsers[$i]->getAttribute($this->config_users['displayname'])[0]);
            $members[$i]->setMail($arUsers[$i]->getAttribute($this->config_users['mail'])[0]);
            $members[$i]->setTel($arUsers[$i]->getAttribute($this->config_users['tel'])[0]);
            $members[$i]->setMember(TRUE);
            $members[$i]->setAdmin(FALSE);
           
            // Idem pour groupini
            $membersini[$i] = new Member();
            $membersini[$i]->setUid($arUsers[$i]->getAttribute($this->config_users['login'])[0]);
            $membersini[$i]->setDisplayname($arUsers[$i]->getAttribute($this->config_users['displayname'])[0]);
            $membersini[$i]->setMail($arUsers[$i]->getAttribute($this->config_users['mail'])[0]);
            $membersini[$i]->setTel($arUsers[$i]->getAttribute($this->config_users['tel'])[0]);
            $membersini[$i]->setMember(TRUE);
            $membersini[$i]->setAdmin(FALSE);
        }
        // on remplit les groupes
        $group ->setMembers($members);
        $groupini ->setMembers($membersini);
                      
        // Création du formulaire de mise à jour
        $editForm = $this->createForm(PrivateGroupEditType::class, $group, array(
            'action' => $this->generateUrl('private_group_update', array('cn'=> $cn)),
            'method' => 'POST',));

        $editForm->handleRequest($request);
        if (($editForm->isSubmitted()) && ($editForm->isValid())) {
            // Récupération des données du formulaire
            $groupupdate = new Group();
            $groupupdate = $editForm->getData();
            
            // Log Mise à jour des membres du groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
            
            // Récup des appartenances
            $m_update = new ArrayCollection();      
            $m_update = $groupupdate->getMembers();
            
            // Nombre de membres
            $nb_memb = sizeof($m_update);
            
            // Mise à jour des membres et admins
            for ($i=0; $i<sizeof($m_update); $i++){
                $memb = $m_update[$i];
                $membi = $membersini[$i];
                $dn_group = $this->config_groups['cn']."=" . $cn . ", ".$this->config_private['private_branch'].", ".$this->config_groups['group_branch'].", ".$this->base;
                $u = $memb->getUid();
                
                // Traitement des membres
                // Si il y a changement pour le membre, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getMember() != $membi->getMember()) {
                    if ($memb->getMember()) {
                        $r = $ldapfonctions->addMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $u ");
                            $nb_memb++;
                        }
                        else {
                            // Message de notification
                            $this->get('session')->getFlashBag()->add('flash-error', 'Erreur  lors de l\'ajout uid='.$u);
                            syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $u ");
                        }
                    }
                    else {
                        $r = $ldapfonctions->delMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "del_member by $adm : group : $cn, user : $u ");
                            $nb_memb--;
                        }
                        else {
                            // Message de notification
                            $this->get('session')->getFlashBag()->add('flash-error', 'Erreur  lors de la suppression uid='.$u);
                            syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $cn, user : $u ");
                        }
                    }
                }
            }
            // Ferme fichier de log
            closelog();

            // Message de notification
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');

            // Retour à l'affichage group_update
            return $this->redirect($this->generateUrl('private_group_update', array('cn'=>$cn)));
        }

        return array(
            'group'      => $group,
            'nb_membres' => sizeof($arUsers),
            'form'   => $editForm->createView()
            ); 

    }
    
    /**
     * Mettre à jour les membres d'un groupe 
     *
     * @Route("/update/{cn}/{liste}", name="group_update")
     * @Template("Group/update.html.twig")     */
    public function updateAction(Request $request, LdapFonctions $ldapfonctions, $cn, $liste="")
    {
        $this->init_config();
        $group = new Group();
        $group->setCn($cn);
        $members = new ArrayCollection();
        
        // Groupe initial pour détecter les modifications
        $groupini = new Group();
        $groupini->setCn($cn);
        $membersini = new ArrayCollection();

// On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        $flag = "nok";
        // Dans le cas d'un gestionnaire
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

        // Récup du filtre amugroupfilter pour affichage
        $amugroupfilter = $ldapfonctions->getAmuGroupFilter($cn);
        $group->setAmugroupfilter($amugroupfilter);
               
        // Recherche des membres dans le LDAP
        $arUsers = $ldapfonctions->getMembersGroup($cn);
        $nb_members = sizeof($arUsers);
        
        // Recherche des admins dans le LDAP
        $nb_admins = 0;
        $arAdmins = $ldapfonctions->getAdminsGroup($cn);
        $flagMembers = array();
        if (null !== $arAdmins[0]->getAttribute($this->config_groups['groupadmin'])) {
            $nb_admins = sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin']));
            for ($i = 0; $i < $nb_admins; $i++) {
                $flagMembers[$i] = FALSE;
            }
        }
        
        // Affichage des membres  
        for ($i=0; $i<$nb_members; $i++) {
            $members[$i] = new Member();
            $members[$i]->setUid($arUsers[$i]->getAttribute($this->config_users['login'])[0]);
            $members[$i]->setDisplayname($arUsers[$i]->getAttribute($this->config_users['displayname'])[0]);
            if (isset($arUsers[$i]->getAttribute($this->config_users['mail'])[0]))
                $members[$i]->setMail($arUsers[$i]->getAttribute($this->config_users['mail'])[0]);
            else
                $members[$i]->setMail("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['tel'])[0]))
                $members[$i]->setTel($arUsers[$i]->getAttribute($this->config_users['tel'])[0]);
            else
                $members[$i]->setTel("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['aff'])[0]))
                $members[$i]->setAff($arUsers[$i]->getAttribute($this->config_users['aff'])[0]);
            else
                $members[$i]->setAff("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['primaff'])[0]))
                $members[$i]->setPrimAff($arUsers[$i]->getAttribute($this->config_users['primaff'])[0]);
            else
                $members[$i]->setPrimAff("");
            $members[$i]->setMember(TRUE);
            $members[$i]->setAdmin(FALSE);
           
            // Idem pour groupini
            $membersini[$i] = new Member();
            $membersini[$i]->setUid($arUsers[$i]->getAttribute($this->config_users['login'])[0]);
            $membersini[$i]->setDisplayname($arUsers[$i]->getAttribute($this->config_users['displayname'])[0]);
            if (isset($arUsers[$i]->getAttribute($this->config_users['mail'])[0]))
                $membersini[$i]->setMail($arUsers[$i]->getAttribute($this->config_users['mail'])[0]);
            else
                $membersini[$i]->setMail("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['tel'])[0]))
                $membersini[$i]->setTel($arUsers[$i]->getAttribute($this->config_users['tel'])[0]);
            else
                $membersini[$i]->setTel("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['aff'])[0]))
                $membersini[$i]->setAff($arUsers[$i]->getAttribute($this->config_users['aff'])[0]);
            else
                $membersini[$i]->setAff("");
            if (isset($arUsers[$i]->getAttribute($this->config_users['primaff'])[0]))
                $membersini[$i]->setPrimAff($arUsers[$i]->getAttribute($this->config_users['primaff'])[0]);
            else
                $membersini[$i]->setPrimAff("");
            $membersini[$i]->setMember(TRUE);
            $membersini[$i]->setAdmin(FALSE);
            
            // on teste si le membre est aussi admin
            if (null !== $arAdmins[0]->getAttribute($this->config_groups['groupadmin'])) {
                for ($j = 0; $j < sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])); $j++) {
                    $uid = preg_replace("/(".$this->config_users['login']."=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", strtolower($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])[$j]));
                    if ($uid == $arUsers[$i]->getAttribute($this->config_users['login'])[0]) {
                        $members[$i]->setAdmin(TRUE);
                        $membersini[$i]->setAdmin(TRUE);
                        $flagMembers[$j] = TRUE;
                        break;
                    }
                }
            }
        }
                
        // Affichage des admins qui ne sont pas membres
        if (null !== $arAdmins[0]->getAttribute($this->config_groups['groupadmin'])) {
            for ($j = 0; $j < sizeof($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])); $j++) {
                if ($flagMembers[$j] == FALSE) {
                    // si l'admin n'est pas membre du groupe, il faut aller récupérer ses infos dans le LDAP
                    $uid = preg_replace("/(".$this->config_users['login']."=)(([A-Za-z0-9:._-]{1,}))(,ou=.*)/", "$3", strtolower($arAdmins[0]->getAttribute($this->config_groups['groupadmin'])[$j]));
                    $result = $ldapfonctions->getInfosUser($uid);
                    $memb = new Member();
                    $memb->setUid($result[0]->getAttribute($this->config_users['login'])[0]);
                    $memb->setDisplayname($result[0]->getAttribute($this->config_users['displayname'])[0]);
                    if (isset($result[0]->getAttribute($this->config_users['mail'])[0]))
                        $memb->setMail($result[0]->getAttribute($this->config_users['mail'])[0]);
                    else
                        $memb->setMail("");
                    if (isset($result[0]->getAttribute($this->config_users['tel'])[0]))
                        $memb->setTel($result[0]->getAttribute($this->config_users['tel'])[0]);
                    else
                        $memb->setTel("");
                    if (isset($result[0]->getAttribute($this->config_users['aff'])[0]))
                        $memb->setAff($result[0]->getAttribute($this->config_users['aff'])[0]);
                    else
                        $memb->setAff("");
                    if (isset($result[0]->getAttribute($this->config_users['primaff'])[0]))
                        $memb->setPrimAff($result[0]->getAttribute($this->config_users['primaff'])[0]);
                    else
                        $memb->setPrimAff("");
                    $memb->setMember(FALSE);
                    $memb->setAdmin(TRUE);
                    $members[] = $memb;

                    // Idem pour groupini
                    $membini = new Member();
                    $membini->setUid($result[0]->getAttribute($this->config_users['login'])[0]);
                    $membini->setDisplayname($result[0]->getAttribute($this->config_users['displayname'])[0]);
                    if (isset($result[0]->getAttribute($this->config_users['mail'])[0]))
                        $membini->setMail($result[0]->getAttribute($this->config_users['mail'])[0]);
                    else
                        $membini->setMail("");
                    if (isset($result[0]->getAttribute($this->config_users['tel'])[0]))
                        $membini->setTel($result[0]->getAttribute($this->config_users['tel'])[0]);
                    else
                        $membini->setTel("");
                    if (isset($result[0]->getAttribute($this->config_users['aff'])[0]))
                        $membini->setAff($result[0]->getAttribute($this->config_users['aff'])[0]);
                    else
                        $membini->setAff("");
                    if (isset($result[0]->getAttribute($this->config_users['primaff'])[0]))
                        $membini->setPrimAff($result[0]->getAttribute($this->config_users['primaff'])[0]);
                    else
                        $membini->setPrimAff("");
                    $membini->setMember(FALSE);
                    $membini->setAdmin(TRUE);
                    $membersini[] = $membini;
                }
            }
        }
        
        $group ->setMembers($members);
        $groupini ->setMembers($membersini);
                      
        // Création du formulaire de mise à jour du groupe
        $editForm = $this->createForm(GroupEditType::class, $group, array(
            'action' => $this->generateUrl('group_update', array('cn'=> $cn)),
            'method' => 'POST',));

        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            echo "Formulaire validé";
            $groupupdate = new Group();
            $groupupdate = $editForm->getData();
            
            // Log Mise à jour des membres du groupe
            openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
            $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");
            
            $m_update = new ArrayCollection();      
            $m_update = $groupupdate->getMembers();
            
            $nb_memb = sizeof($m_update);
            
            // on parcourt tous les membres
            for ($i=0; $i<sizeof($m_update); $i++)
            {
                $memb = $m_update[$i];
                $membi = $membersini[$i];
                $dn_group = $this->config_groups['cn']."=" . $cn . ", ".$this->config_groups['group_branch'].", ".$this->base;
                $u = $memb->getUid();

                // Traitement des membres
                // Si il y a changement pour le membre, on modifie dans le ldap, sinon, on ne fait rien
                if ($memb->getMember() != $membi->getMember()) {
                    if ($memb->getMember()) {
                        $r = $ldapfonctions->addMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "add_member by $adm : group : $cn, user : $u ");
                            $nb_memb++;
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : add_member by $adm : group : $cn, user : $u ");
                        }
                    }
                    else {
                        $r = $ldapfonctions->delMemberGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "del_member by $adm : group : $cn, user : $u ");
                            $nb_memb--;
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $cn, user : $u ");
                        }
                    }
                }
                // Traitement des admins
                // Idem : si changement, on répercute dans le ldap
                if ($memb->getAdmin() != $membi->getAdmin()) {
                    if ($memb->getAdmin()) {
                        $r = $ldapfonctions->addAdminGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "add_admin by $adm : group : $cn, user : $u ");
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : add_admin by $adm : group : $cn, user : $u ");
                        }
                    }
                    else {
                        $r = $ldapfonctions->delAdminGroup($dn_group, array($u));
                        if ($r) {
                            // Log modif
                            syslog(LOG_INFO, "del_admin by $adm : group : $cn, user : $u ");
                        }
                        else {
                            syslog(LOG_ERR, "LDAP ERROR : del_admin by $adm : group : $cn, user : $u ");
                        }
                    }
                }
            }
            // Ferme fichier de log
            closelog();
            
            $this->get('session')->getFlashBag()->add('flash-notice', 'Les modifications ont bien été enregistrées');       

            // Retour à l'affichage group_update
            return $this->redirect($this->generateUrl('group_update', array('cn'=>$cn, 'liste'=>$liste)));
        }

        return array(
            'group' => $group,
            'nb_membres' => $nb_members,
            'nb_admins' => $nb_admins,
            'form' => $editForm->createView(),
            'liste' => $liste
            );

    }

    /**
     * Vider un groupe de ses membres
     *
     * @Route("/empty/{cn}/{liste}", name="group_empty")*/
    public function emptyAction(Request $request, LdapFonctions $ldapfonctions, $cn, $liste="")
    {
        $this->init_config();
        $group = new Group();
        $group->setCn($cn);
        $members = new ArrayCollection();

        // On déclare le LDAP
        try {
            $ldap = Ldap::create('ext_ldap', array('connection_string' => getenv("connection_string")));
            $ldap->bind(getenv("relative_dn"), getenv("ldappassword"));
        }catch (ConnectionException $e) {
            throw new \Exception(sprintf('Erreur connexion LDAP.'), 0, $e);
        }

        // On récupère le service ldapfonctions
        $ldapfonctions->SetLdap($ldap, getenv("base_dn"), $this->config_users, $this->config_groups, $this->config_private);

        $flag = "nok";
        // Dans le cas d'un gestionnaire
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
            // Retour à'accueil
            $this->get('session')->getFlashBag()->add('flash-error', 'Vous n\'avez pas les droits pour effectuer cette opétion');
            return $this->redirect($this->generateUrl('homepage'));
        }

        // Recherche des membres dans le LDAP
        $arUsers = $ldapfonctions->getMembersGroup($cn);
        $nb_members = sizeof($arUsers);

        // Log Mise àour des membres du groupe
        openlog("groupie-v2", LOG_PID | LOG_PERROR, constant($this->config_logs['facility']));
        $adm = $this->container->get('security.token_storage')->getToken()->getAttribute("uid");

        // on parcourt tous les membres
        for ($i=0; $i<$nb_members; $i++)
        {
            // Recup uid des membres
            $u = $arUsers[$i]->getAttribute($this->config_users['login'])[0];
            $dn_group = $this->config_groups['cn']."=" . $cn . ", ".$this->config_groups['group_branch'].", ".$this->base;

            // Suppression des membres
            $r = $ldapfonctions->delMemberGroup($dn_group, array($u));
            if ($r) {
                // Log modif
                syslog(LOG_INFO, "del_member by $adm : group : $cn, user : $u ");
            }
            else {
                syslog(LOG_ERR, "LDAP ERROR : del_member by $adm : group : $cn, user : $u ");
            }

        }
        // Ferme fichier de log
        closelog();

        $this->get('session')->getFlashBag()->add('flash-notice', 'Les membres du groupe ont bien ete supprimes');

        // Retour affichage group_update
	return $this->redirect($this->generateUrl('group_update', array('cn'=>$cn, 'liste'=>$liste)));

    }
    
    /** 
    * Affichage du document d'aide
    *
    * @Route("/help",name="help")
    */
    public function helpAction() {
        return $this->render('Group/help.html.twig');
    }
    
    /** 
    * Affichage du document d'aide concernant les groupes privés
    *
    * @Route("/private_help",name="private_help")
    */
    public function privatehelpAction() {
        return $this->render('Group/privatehelp.html.twig');
    }
    
}
