<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\MotifIntervention;
use App\Form\References\MotifInterventionType;

class MotifInterventionController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/motif_intervention", methods={"POST"}, name="ajax-reference-motif_intervention-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, MotifIntervention::class, MotifInterventionType::class);
    }

    /**
     * @Route("/ajax/reference/motif_intervention/{reference}", methods={"PUT"}, name="ajax-reference-motif_intervention-modifier")
     */
    public function modifier(MotifIntervention $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, MotifIntervention::class, MotifInterventionType::class);
    }

    /**
     * @Route("/ajax/reference/motif_intervention/{reference}", methods={"DELETE"}, name="ajax-reference-motif_intervention-supprimer")
     */
    public function supprimer(MotifIntervention $reference): JsonResponse
    {
        return $this->supprimerReference($reference, MotifIntervention::class);
    }
}
