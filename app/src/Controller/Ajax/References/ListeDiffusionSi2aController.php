<?php

namespace App\Controller\Ajax\References;

use App\Entity\References\ListeDiffusionSi2a;
use App\Form\References\ListeDiffusionSi2aType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ListeDiffusionSi2aController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/liste_diffusion_si2a", methods={"POST"}, name="ajax-reference-liste_diffusion_si2a-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, ListeDiffusionSi2a::class, ListeDiffusionSi2aType::class);
    }

    /**
     * @Route("/ajax/reference/liste_diffusion_si2a/{reference}", methods={"PUT"}, name="ajax-reference-liste_diffusion_si2a-modifier")
     */
    public function modifier(ListeDiffusionSi2a $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, ListeDiffusionSi2a::class, ListeDiffusionSi2aType::class);
    }

    /**
     * @Route("/ajax/reference/liste_diffusion_si2a/{reference}", methods={"DELETE"}, name="ajax-reference-liste_diffusion_si2a-supprimer")
     */
    public function supprimer(ListeDiffusionSi2a $reference): JsonResponse
    {
        return $this->supprimerReference($reference, ListeDiffusionSi2a::class);
    }
}
