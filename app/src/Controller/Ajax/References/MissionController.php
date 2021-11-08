<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\Mission;
use App\Form\References\MissionType;

class MissionController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/mission", methods={"POST"}, name="ajax-reference-mission-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, Mission::class, MissionType::class);
    }

    /**
     * @Route("/ajax/reference/mission/{reference}", methods={"PUT"}, name="ajax-reference-mission-modifier")
     */
    public function modifier(Mission $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, Mission::class, MissionType::class);
    }

    /**
     * @Route("/ajax/reference/mission/{reference}", methods={"DELETE"}, name="ajax-reference-mission-supprimer")
     */
    public function supprimer(Mission $reference): JsonResponse
    {
        return $this->supprimerReference($reference, Mission::class);
    }
}
