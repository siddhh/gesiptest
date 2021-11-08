<?php

namespace App\Controller;

use App\Entity\Documentation\Fichier;
use App\Entity\Documentation\Document;
use App\Form\Documentation\DocumentType;
use App\Service\DocumentationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;

class DocumentationController extends AbstractController
{

    /**
     * @Route("/documentation/liste", name="documentation-liste-voir")
     */
    public function listeDocuments(): Response
    {
        // Initialisation
        $listeDocuments = $this->getDoctrine()->getRepository(Document::class)->findBy(['supprimeLe' => null], ['titre' => 'asc']);

        // Restitution
        return $this->render(
            'documentation/liste.html.twig',
            ['listeDocuments'    => $listeDocuments]
        );
    }

    /**
     * @Route("/documentation/telecharger/{hash}", name="documentation-fichier-telecharger")
     */
    public function telecharger(string $hash): BinaryFileResponse
    {
        // Vérifie si le fichier demandé n'a pas été supprimé ou si sa documentation n'est pas supprimée
        $fichier = $this->getDoctrine()->getRepository(Fichier::class)->findOneBy(['hash' => $hash]);
        if (($fichier == null) || ($fichier->getSupprimeLe() != null) || ($fichier->getDocument() == null) || ($fichier->getDocument()->getSupprimeLe() != null)) {
            throw new NotFoundHttpException();
        }

        // Forme le nom du fichier, et récupère l'endroit ou récupérer les données
        $nomFichier = $fichier->getLabel() . ($fichier->getExtension() == null ? '' : '.' . $fichier->getExtension());
        $cheminFichier = $this->getParameter('documentation_directory') . DIRECTORY_SEPARATOR . $fichier->getHash();

        // Retourne la réponse sous forme de fichier binaire (stream, etag,..)
        $response = new BinaryFileResponse($cheminFichier);
        $response->headers->set('Content-Type', $fichier->getMimeType());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $nomFichier
        );
        return $response;
    }

    /**
     * @Route("/gestion/documentation/creer", name="documentation-document-creer")
     */
    public function creationDocument(Request $request, DocumentationService $documentationService): Response
    {
        // On créé le document vide
        $document = new Document();
        $document->setDate(new \DateTime('now', new \DateTimeZone("Europe/Paris")));
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

         // Si valide, on persiste l'état du document en base de données
        if ($form->isSubmitted() && $form->isValid()) {
            $document = $form->getData();
            $documentFichiers = $document->getFichiers();
            $em = $this->getDoctrine()->getManager();
            $em->persist($document);

            // Parcours les forms fichiers renvoyés dans le formulaire à récupérer
            foreach ($form->get('fichiers') as $index => $formFichier) {
                // Si le fichier vient d'être ajouté, le champ fichier n'est pas vide
                $fichierData = $formFichier->get('fichier')->getData();
                if (!empty($fichierData)) {
                    $fichier = $documentFichiers->toArray()[$index];
                    $fichier = $documentationService->enregistre($fichier, $fichierData);
                    $fichier->setDocument($document);
                    $em->persist($fichier);
                }
            }

            // Enregistre la création du document en base de données
            $em->persist($document);
            $em->flush();
            $this->addFlash(
                'success',
                "Le document {$document->getTitre()} a bien été créé."
            );

            // Retourne à la liste des documents
            return $this->redirectToRoute('documentation-liste-voir');
        }
         // Retourne à la page web
         return $this->render('documentation/modifier.html.twig', [
            'form'                  => $form->createView(),
            'document'              => $document,
            'mimeTypesAutorises'    => DocumentationService::mimeTypesAutorises(),
            'extensionsAutorisees'  => DocumentationService::extensionsAutorisees(),
            'action'                => 'ajouter',
         ]);
    }

    /**
     * @Route("/gestion/documentation/modifier/{document}", name="documentation-document-modifier")
     */
    public function modifierDocument(Document $document, Request $request, DocumentationService $documentationService): Response
    {
        // Récupère le document sans ses fichiers supprimés
        $em = $this->getDoctrine()->getManager();
        $document = $em->getRepository(Document::class)->getActiveDocument($document->getId());
        if (empty($document)) {
            throw $this->createNotFoundException('Document inexistant ou supprimé.');
        }

        // Récupère la collection de fichiers initiale
        $initialFichiers = [];
        foreach ($document->getFichiers() as $fichier) {
            $initialFichiers[] = $fichier;
        }

        // Rempli le formulaire avec le document concerné
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        // Si valide, on persiste l'état du document en base de données
        if ($form->isSubmitted() && $form->isValid()) {
            $document = $form->getData();
            $documentFichiers = $document->getFichiers();

            // Parcours les fichiers pour supprimer ceux qui n'existe plus !
            foreach ($initialFichiers as $fichier) {
                if (false === $document->getFichiers()->contains($fichier)) {
                    $fichier->setSupprimeLe(new \DateTime());
                }
            }
            // Parcours les forms fichiers renvoyés dans le formulaire à récupérer
            foreach ($form->get('fichiers') as $index => $formFichier) {
                // Si le fichier vient d'être ajouté, le champ fichier n'est pas vide
                $fichierData = $formFichier->get('fichier')->getData();
                if (!empty($fichierData)) {
                    $fichier = $documentFichiers->toArray()[$index];
                    $fichier = $documentationService->enregistre($fichier, $fichierData);
                    $fichier->setDocument($document);
                    $em->persist($fichier);
                }
            }

            // Enregistre les modifications en base de données
            $em->flush();

            $this->addFlash(
                'success',
                "Le document {$document->getTitre()} a bien été modifié."
            );
            // Si tout est ok, on réactualise la page
            return $this->redirectToRoute('documentation-document-modifier', ['document' => $document->getId()]);
        }
        // Retourne la page web
        return $this->render('documentation/modifier.html.twig', [
            'form'                  => $form->createView(),
            'document'              => $document,
            'mimeTypesAutorises'    => DocumentationService::mimeTypesAutorises(),
            'extensionsAutorisees'  => DocumentationService::extensionsAutorisees(),
            'action'                => 'modifier',
        ]);
    }
}
