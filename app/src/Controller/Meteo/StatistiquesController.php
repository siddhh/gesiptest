<?php

namespace App\Controller\Meteo;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatistiquesController extends AbstractController
{
    /**
     * @Route("/meteo/statistiques", name="meteo-statistiques")
     */
    public function index(): Response
    {
        return $this->render('meteo/statistiques/index.html.twig');
    }
}
