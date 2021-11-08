<?php

namespace App\Controller\Ajax\References;

use App\Entity\References\GridMep;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\References\GridMepType;

class GridMepController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/grid_mep", methods={"POST"}, name="ajax-reference-grid_mep-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, GridMep::class, GridMepType::class);
    }

    /**
     * @Route("/ajax/reference/grid_mep/{reference}", methods={"PUT"}, name="ajax-reference-grid_mep-modifier")
     */
    public function modifier(GridMep $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, GridMep::class, GridMepType::class);
    }

    /**
     * @Route("/ajax/reference/grid_mep/{reference}", methods={"DELETE"}, name="ajax-reference-grid_mep-supprimer")
     */
    public function supprimer(GridMep $reference): JsonResponse
    {
        return $this->supprimerReference($reference, GridMep::class);
    }
}
