<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\ImpactMeteo;
use App\Form\References\ImpactMeteoType;

class ImpactMeteoController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/impact_meteo", methods={"POST"}, name="ajax-reference-impact_meteo-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, ImpactMeteo::class, ImpactMeteoType::class);
    }

    /**
     * @Route("/ajax/reference/impact_meteo/{reference}", methods={"PUT"}, name="ajax-reference-impact_meteo-modifier")
     */
    public function modifier(ImpactMeteo $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, ImpactMeteo::class, ImpactMeteoType::class);
    }

    /**
     * @Route("/ajax/reference/impact_meteo/{reference}", methods={"DELETE"}, name="ajax-reference-impact_meteo-supprimer")
     */
    public function supprimer(ImpactMeteo $reference): JsonResponse
    {
        return $this->supprimerReference($reference, ImpactMeteo::class);
    }
}
