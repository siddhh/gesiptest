<?php

namespace App\Controller\Ajax\Documentation;

use App\Entity\Documentation\Document;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class DocumentationController extends AbstractController
{
    /**
     * @Route("/ajax/documentation/supprimer/{document}", methods={"POST"}, name="ajax-documentation-supprimer")
     */
    public function supprimerDocument(Document $document)
    {
        $now = new \DateTime();
        $document->setSupprimeLe($now);
        foreach ($document->getFichiers() as $fichier) {
            $fichier->setSupprimeLe($now);
        }
        $this->getDoctrine()->getManager()->flush();

        $reponse = ["ok"];
        return new JsonResponse($reponse);
    }
}
