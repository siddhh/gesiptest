<?php

namespace App\Controller\Meteo;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\Meteo\PublicationType;

class PublicationController extends AbstractController
{
    /**
     * @Route("/meteo/publication", name="meteo-publication")
     */
    public function index(): Response
    {
        $form = $this->createForm(PublicationType::class);

        return $this->render('meteo/publication/publication.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
