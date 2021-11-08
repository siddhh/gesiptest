<?php

namespace App\Controller\Gestion\Fiabilisation;

use App\Entity\Service;
use App\Form\Fiabilisation\RechercheServicesSollicitationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SollicitationController extends AbstractController
{

    /**
     * @Route("/gestion/fiabilisation/sollicitation", name="gestion-fiabilisation-sollicitation-recherche")
     */
    public function recherche(Request $request): Response
    {
        /** @var Service $serviceCourant */
        $serviceCourant = $this->getUser();
        $searchForm = $this->createForm(RechercheServicesSollicitationType::class);
        $searchForm->handleRequest($request);
        $filters = [];

        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $filters = $searchForm->getData();
        } else {
            /** @var Service $user */
            if ($serviceCourant->getEstPilotageDme() && !in_array(Service::ROLE_ADMIN, $serviceCourant->getRoles())) {
                $filters['equipe'] = $serviceCourant;
                $searchForm->get('equipe')->setData($serviceCourant);
            }
        }

        $services = $this->getDoctrine()
            ->getRepository(Service::class)
            ->rechercheSollicitationServices($filters);

        return $this->render('gestion/fiabilisation/sollicitation/recherche.html.twig', [
            'searchForm' => $searchForm->createView(),
            'services' => $services
        ]);
    }
}
