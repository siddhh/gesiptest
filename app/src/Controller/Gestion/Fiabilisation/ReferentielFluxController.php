<?php

namespace App\Controller\Gestion\Fiabilisation;

use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\Service;
use App\Form\Fiabilisation\RechercheDemandeReferentielFluxType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReferentielFluxController extends AbstractController
{

    /**
     * @Route("/gestion/fiabilisation/flux", name="gestion-fiabilisation-flux-index")
     */
    public function index(): Response
    {
        $nbDemandes = $this->getDoctrine()->getRepository(DemandeReferentielFlux::class)
            ->nombreDemandesEnAttente();

        return $this->render('gestion/fiabilisation/flux/index.html.twig', [
            'nbDemandesEnAttente' => $nbDemandes
        ]);
    }

    /**
     * @Route("/gestion/fiabilisation/flux/liste", name="gestion-fiabilisation-flux-liste")
     */
    public function recherche(Request $request): Response
    {
        /** @var Service $serviceCourant */
        $serviceCourant = $this->getUser();
        $searchForm = $this->createForm(RechercheDemandeReferentielFluxType::class);
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

        $demandes = $this->getDoctrine()
            ->getRepository(DemandeReferentielFlux::class)
            ->rechercheDemandesEnAttente($filters);

        return $this->render('gestion/fiabilisation/flux/recherche.html.twig', [
            'searchForm'    => $searchForm->createView(),
            'demandes'  => $demandes
        ]);
    }
}
