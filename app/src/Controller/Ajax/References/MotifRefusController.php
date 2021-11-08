<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\MotifRefus;
use App\Form\References\MotifRefusType;

class MotifRefusController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/motif_refus", methods={"POST"}, name="ajax-reference-motif_refus-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, MotifRefus::class, MotifRefusType::class);
    }

    /**
     * @Route("/ajax/reference/motif_refus/{reference}", methods={"PUT"}, name="ajax-reference-motif_refus-modifier")
     */
    public function modifier(MotifRefus $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, MotifRefus::class, MotifRefusType::class);
    }

    /**
     * @Route("/ajax/reference/motif_refus/{reference}", methods={"DELETE"}, name="ajax-reference-motif_refus-supprimer")
     */
    public function supprimer(MotifRefus $reference): JsonResponse
    {
        return $this->supprimerReference($reference, MotifRefus::class);
    }
}
