<?php

namespace App\Controller\Gestion\Fiabilisation;

use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\References\Mission;
use App\Entity\Service;
use App\Form\Fiabilisation\RechercheDemandePerimetreApplicatifType;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PerimetreApplicatifController extends AbstractController
{

    /**
     * @Route("/gestion/fiabilisation/applicatif", name="gestion-fiabilisation-applicatif-index")
     */
    public function index(): Response
    {
        $nbDemandes = $this->getDoctrine()->getRepository(DemandePerimetreApplicatif::class)
            ->nombreDemandesEnAttente();

        return $this->render('gestion/fiabilisation/applicatifs/index.html.twig', [
            'nbDemandesEnAttente' => $nbDemandes
        ]);
    }

    /**
     * @Route("/gestion/fiabilisation/applicatif/liste", name="gestion-fiabilisation-applicatif-liste")
     */
    public function recherche(Request $request): Response
    {
        /** @var Service $serviceCourant */
        $serviceCourant = $this->getUser();
        $searchForm = $this->createForm(RechercheDemandePerimetreApplicatifType::class);
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
            ->getRepository(DemandePerimetreApplicatif::class)
            ->rechercheDemandesEnAttente($filters);

        return $this->render('gestion/fiabilisation/applicatifs/recherche.html.twig', [
            'searchForm'    => $searchForm->createView(),
            'demandes'  => $demandes
        ]);
    }

    /**
     * @Route("/gestion/fiabilisation/applicatif/maj", name="gestion-fiabilisation-applicatif-maj")
     */
    public function maj(Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine();
        $listeServices = $em->getRepository(Service::class)->findBy([], [ 'label' => 'asc' ]);
        $listeMissions = $em->getRepository(Mission::class)->findBy([], [ 'label' => 'asc' ]);

        return $this->render('gestion/fiabilisation/applicatifs/maj.html.twig', compact(['listeServices', 'listeMissions']));
    }
}
