<?php

namespace App\Controller\Ajax\References;

use App\Entity\References\Usager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\References\UsagerType;

class UsagerController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/usager", methods={"POST"}, name="ajax-reference-usager-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, Usager::class, UsagerType::class);
    }

    /**
     * @Route("/ajax/reference/usager/{reference}", methods={"PUT"}, name="ajax-reference-usager-modifier")
     */
    public function modifier(Usager $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, Usager::class, UsagerType::class);
    }

    /**
     * @Route("/ajax/reference/usager/{reference}", methods={"DELETE"}, name="ajax-reference-usager-supprimer")
     */
    public function supprimer(Usager $reference): JsonResponse
    {
        return $this->supprimerReference($reference, Usager::class);
    }
}
