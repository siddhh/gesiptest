<?php

namespace App\Controller\Meteo;

use App\Entity\Service;
use App\Form\Meteo\ConsultationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ConsultationMeteoController extends AbstractController
{
    /**
     * @Route("/meteo/consultation", name="meteo-consultation")
     */
    public function consultation(Request $request, Security $security): Response
    {
        // Initialisation
        $serviceConnecte = $security->getUser();
        $listeServicesExploitants = $this->getDoctrine()->getManager()
            ->getRepository(Service::class)->findBy(['estServiceExploitant' => true]);
        $idExploitantSelectionne = null;
        if (in_array($serviceConnecte, $listeServicesExploitants, true) && $security->isGranted(Service::ROLE_INTERVENANT)) {
            $idExploitantSelectionne = $serviceConnecte->getId();
        }
        $form = $this->createForm(ConsultationType::class);

        // Restitution
        return $this->render('meteo/consultation/consultation.html.twig', [
            'form'                  => $form->createView(),
            'listeExploitants'      => $listeServicesExploitants,
            'exploitantSelectionne' => $idExploitantSelectionne
        ]);
    }
}
