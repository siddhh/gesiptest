<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\Profil;
use App\Form\References\ProfilType;

class ProfilController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/profil", methods={"POST"}, name="ajax-reference-profil-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, Profil::class, ProfilType::class);
    }

    /**
     * @Route("/ajax/reference/profil/{reference}", methods={"PUT"}, name="ajax-reference-profil-modifier")
     */
    public function modifier(Profil $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, Profil::class, ProfilType::class);
    }

    /**
     * @Route("/ajax/reference/profil/{reference}", methods={"DELETE"}, name="ajax-reference-profil-supprimer")
     */
    public function supprimer(Profil $reference): JsonResponse
    {
        return $this->supprimerReference($reference, Profil::class);
    }
}
