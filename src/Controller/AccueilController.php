<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class AccueilController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index()
    {
        return $this->render('index.html.twig');
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
