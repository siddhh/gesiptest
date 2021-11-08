<?php

namespace App\Controller\Ajax\References;

use App\Entity\References\StatutMep;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\References\StatutMepType;

class StatutMepController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/statut_mep", methods={"POST"}, name="ajax-reference-statut_mep-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, StatutMep::class, StatutMepType::class);
    }

    /**
     * @Route("/ajax/reference/statut_mep/{reference}", methods={"PUT"}, name="ajax-reference-statut_mep-modifier")
     */
    public function modifier(StatutMep $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, StatutMep::class, StatutMepType::class);
    }

    /**
     * @Route("/ajax/reference/statut_mep/{reference}", methods={"DELETE"}, name="ajax-reference-statut_mep-supprimer")
     */
    public function supprimer(StatutMep $reference): JsonResponse
    {
        return $this->supprimerReference($reference, StatutMep::class);
    }
}
