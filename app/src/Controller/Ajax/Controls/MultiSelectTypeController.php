<?php

namespace App\Controller\Ajax\Controls;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\DemandeIntervention;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MultiSelectTypeController extends AbstractController
{
    /**
     * @Route("/ajax/multi-select-type/composants", methods={"GET"}, name="ajax-multi-select-type-composants")
     */
    public function rechercheComposants(Request $request): JsonResponse
    {
        return new JsonResponse(
            $this->getDoctrine()->getRepository(Composant::class)
                ->multiSelectSearchByLabel($request->get('search'))
        );
    }

    /**
     * @Route("/ajax/multi-select-type/pilotes", methods={"GET"}, name="ajax-multi-select-type-pilotes")
     */
    public function recherchePilotes(Request $request): JsonResponse
    {
        return new JsonResponse(
            $this->getDoctrine()->getRepository(Pilote::class)
                ->multiSelectSearchByNomPrenom($request->get('search'))
        );
    }

     /**
     * @Route("/ajax/multi-select-type/demandes", methods={"GET"}, name="ajax-multi-select-type-demandes")
     */
    public function rechercheDemandes(Request $request): JsonResponse
    {
        return new JsonResponse(
            $this->getDoctrine()->getRepository(DemandeIntervention::class)
                ->multiSelectSearchByLabel($request->get('search'))
        );
    }
}
