<?php

namespace App\Controller\Ajax\References;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\TypeElement;
use App\Form\References\TypeElementType;

class TypeElementController extends ReferenceController
{

    /**
     * @Route("/ajax/reference/type_element", methods={"POST"}, name="ajax-reference-type_element-creer")
     */
    public function creer(Request $request): JsonResponse
    {
        return $this->creerReference($request, TypeElement::class, TypeElementType::class);
    }

    /**
     * @Route("/ajax/reference/type_element/{reference}", methods={"PUT"}, name="ajax-reference-type_element-modifier")
     */
    public function modifier(TypeElement $reference, Request $request): JsonResponse
    {
        return $this->modifierReference($reference, $request, TypeElement::class, TypeElementType::class);
    }

    /**
     * @Route("/ajax/reference/type_element/{reference}", methods={"DELETE"}, name="ajax-reference-type_element-supprimer")
     */
    public function supprimer(TypeElement $reference): JsonResponse
    {
        return $this->supprimerReference($reference, TypeElement::class);
    }
}
