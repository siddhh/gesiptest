<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\MotifRenvoi;
use App\Form\References\MotifRenvoiType;

class MotifRenvoiController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/motif_renvoi", methods={"POST"}, name="ajax-reference-motif_renvoi-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, MotifRenvoi::class, MotifRenvoiType::class);
    }

    /**
     * @Route("/ajax/reference/motif_renvoi/{reference}", methods={"PUT"}, name="ajax-reference-motif_renvoi-modifier")
     */
    public function modifier(MotifRenvoi $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, MotifRenvoi::class, MotifRenvoiType::class);
    }

    /**
     * @Route("/ajax/reference/motif_renvoi/{reference}", methods={"DELETE"}, name="ajax-reference-motif_renvoi-supprimer")
     */
    public function supprimer(MotifRenvoi $reference): JsonResponse
    {
        return $this->supprimerReference($reference, MotifRenvoi::class);
    }
}
