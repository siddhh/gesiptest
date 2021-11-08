<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\Domaine;
use App\Form\References\DomaineType;

class DomaineController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/domaine", methods={"POST"}, name="ajax-reference-domaine-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, Domaine::class, DomaineType::class);
    }

    /**
     * @Route("/ajax/reference/domaine/{reference}", methods={"PUT"}, name="ajax-reference-domaine-modifier")
     */
    public function modifier(Domaine $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, Domaine::class, DomaineType::class);
    }

    /**
     * @Route("/ajax/reference/domaine/{reference}", methods={"DELETE"}, name="ajax-reference-domaine-supprimer")
     */
    public function supprimer(Domaine $reference): JsonResponse
    {
        return $this->supprimerReference($reference, Domaine::class);
    }
}
