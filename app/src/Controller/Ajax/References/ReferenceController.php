<?php

namespace App\Controller\Ajax\References;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\References\Reference;
use Symfony\Component\Form\Form;

abstract class ReferenceController extends AbstractController
{

    /**
     * Créée une nouvelle référence
     */
    protected function creerReference(Request $request, string $referenceClass, string $formReferenceClass): JsonResponse
    {
        $reference = new $referenceClass();
        $form = $this->createForm($formReferenceClass, $reference);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Instancie et persiste une nouvelle référence
            $reference = $form->getData();
            $reference->setAjouteLe(new \DateTime());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($reference);
            $entityManager->flush();
            return $this->retourneReponse(
                Response::HTTP_CREATED,
                true,
                [],
                ['nouvelId' => $reference->getId()]
            );
        }
        // formulaire non valide ou non soumis correctement
        return $this->retourneReponse(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            false,
            self::getErreurMessages($form)
        );
    }

    /**
     * Modifier une référence existante
     */
    protected function modifierReference(Reference $ancienneReference, Request $request, string $referenceClass, string $formReferenceClass): JsonResponse
    {
        $entityManager = $this->getDoctrine()->getManager();
        if (!$ancienneReference->getSupprimeLe()) {
            $nouvelleReference = new $referenceClass();
            $form = $this->createForm($formReferenceClass, $nouvelleReference, ['method' => 'PUT']);
            $supprimeId = $ancienneReference->getId();
            $ancienneReference->setSupprimeLe(new \DateTime());
            $entityManager->flush();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                // Instancie et persiste une nouvelle référence
                $nouvelleReference->setAjouteLe(new \DateTime());
                $entityManager->persist($nouvelleReference);
                $entityManager->flush();
                // modification ok
                return $this->retourneReponse(
                    Response::HTTP_CREATED,
                    true,
                    [],
                    [
                        'nouvelId'      => $nouvelleReference->getId(),
                        'supprimeId'   => $supprimeId,
                    ]
                );
            } else {
                $ancienneReference->setSupprimeLe(null);
                $entityManager->flush();
            }
            // formulaire non valide ou non soumis correctement
            return $this->retourneReponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                false,
                self::getErreurMessages($form)
            );
        } else {
            // tentative de modification d'une référence déjà supprimée
            return $this->retourneReponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                false,
                [
                    'Cette référence est déjà supprimée, impossible de la modifier !'
                ]
            );
        }
    }

    /**
     * Supprime une référence existante (suppression "douce")
     */
    protected function supprimerReference(Reference $reference, string $referenceClass): JsonResponse
    {
        if (!$reference->getSupprimeLe()) {
            $supprimeId = $reference->getId();
            $entityManager = $this->getDoctrine()->getManager();
            $reference->setSupprimeLe(new \DateTime());
            $entityManager->flush();
            return $this->retourneReponse(
                Response::HTTP_OK,
                true,
                [],
                [
                    'supprime_id' => $supprimeId
                ]
            );
        } else {
            return $this->retourneReponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                false,
                [
                    'Cette référence est déjà supprimée !'
                ]
            );
        }
    }

    /**
     * Méthode helper générant une réponse standardisée
     */
    private function retourneReponse(int $httpStatus, bool $success, array $errors = [], array $optionalData = []): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'errors' => $errors,
            'data' => array_merge(
                $optionalData
            )
        ], $httpStatus);
    }

    /**
     * Méthode permettant de retourner les messages d'erreurs à partir d un formulaire
     */
    private static function getErreurMessages(Form $form): array
    {
        $erreurMessages = [];
        foreach ($form->getErrors(true) as $formError) {
            $erreurMessages[] = $formError->getMessage();
        }
        return $erreurMessages;
    }
}
