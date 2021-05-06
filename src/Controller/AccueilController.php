<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class AccueilController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index()
    {
        if($this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
            // Pour les gestionnaires de groupes, on affiche les groupes qu'ils gèrent
            return $this->redirectToRoute('my_groups');
        }
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Pour les autres, on affiche les groupes dont ils sont membres
            return $this->redirectToRoute('memberships');
        }
    }

    /**
     * @Route("/search", name="search")
     */
    public function search()
    {
        return $this->render('accueil_recherche.html.twig');
    }

    /**
     * @Route("/private", name="private")
     */
    public function private()
    {
        return $this->render('accueil_groupes_prives.html.twig');
    }

    /**
     * @Route("/gestion", name="gestion")
     */
    public function gestion()
    {
        return $this->render('accueil_gestion_groupes.html.twig');
    }
}
