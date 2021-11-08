<?php

namespace App\Controller\Fiabilisation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\Mission;

class PerimetreApplicatifController extends AbstractController
{

    /**
     * @Route("/fiabilisation/applicatif/demandes", name="fiabilisation-applicatif-demandes")
     */

    public function demandesService(Request $request): Response
    {
        $listeMissions = $this->getDoctrine()->getRepository(Mission::class)->listeToutesMissions();
        $response = $this->render('fiabilisation/applicatifs/demandesService.html.twig', [
            'listeMissions' => $listeMissions
        ]);
        return $response;
    }
}
