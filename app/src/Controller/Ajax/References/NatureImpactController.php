<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\NatureImpact;
use App\Form\References\NatureImpactType;

class NatureImpactController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/nature_impact", methods={"POST"}, name="ajax-reference-nature_impact-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, NatureImpact::class, NatureImpactType::class);
    }

    /**
     * @Route("/ajax/reference/nature_impact/{reference}", methods={"PUT"}, name="ajax-reference-nature_impact-modifier")
     */
    public function modifier(NatureImpact $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, NatureImpact::class, NatureImpactType::class);
    }

    /**
     * @Route("/ajax/reference/nature_impact/{reference}", methods={"DELETE"}, name="ajax-reference-nature_impact-supprimer")
     */
    public function supprimer(NatureImpact $reference): JsonResponse
    {
        return $this->supprimerReference($reference, NatureImpact::class);
    }
}
