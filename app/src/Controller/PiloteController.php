<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Pilote;
use App\Form\PiloteType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PiloteController extends AbstractController
{
    /**
     * @Route("/gestion/pilotes", name="gestion-pilotes-liste")
     */
    public function index(): Response
    {
        return $this->render('gestion/pilotes/liste.html.twig');
    }

    /**
     * @Route("/gestion/pilotes/creation", name="gestion-pilotes-creation")
     */
    public function creationPilote(Request $request, UrlGeneratorInterface $router): Response
    {
        $pilote = new Pilote();
        $form = $this->createForm(PiloteType::class, $pilote);
        $form->handleRequest($request);
        $response = new Response();
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($pilote);
            $em->flush();
            $this->addFlash(
                'success',
                "Le pilote {$pilote->getNomCompletCourt()} a bien été créé."
            );
            // redirige vers la liste des pilotes
            return $this->redirectToRoute('gestion-pilotes-liste');
        }

        return $this->render('gestion/pilotes/creation.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/gestion/pilotes/{pilote}/modifier", name="gestion-pilotes-modification")
     * Permet de modifier un pilote existant
     */
    public function modifierPilote(Request $request, Pilote $pilote): Response
    {
        // génère le formulaire à partir des données du pilote
        $form = $this->createForm(PiloteType::class, $pilote);
        // récupère les paramètres fournis par le user
        $form->handleRequest($request);
        // si valide, on persiste l'état du pilote en base de données
        if ($form->isSubmitted() && $form->isValid()) {
            $pilote = $form->getData();
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($pilote);
            $entityManager->flush();
            $this->addFlash(
                'success',
                "Le pilote {$pilote->getNomCompletCourt()} a bien été modifié."
            );
            // redirige vers la liste des pilotes
            return $this->redirectToRoute('gestion-pilotes-liste');
        }
        // Retourne la page web
        return $this->render('gestion/pilotes/modification.html.twig', [
            'form'      => $form->createView(),
            'pilote'   => $pilote
        ]);
    }

    /**
     * @Route("/gestion/pilotes/{pilote}/supprimer", name="gestion-pilotes-suppression")
     * Supprime le pilote
     */
    public function suppression(Pilote $pilote)
    {
        // crée la date de suppression
        $pilote->setSupprimeLe(new \DateTime());
        $em = $this->getDoctrine()->getManager();
        $em->persist($pilote);
        $em->flush();

        //flash de la suppression de pilote
        $this->addFlash(
            'success',
            "Le pilote {$pilote->getNomCompletCourt()} a bien été supprimé."
        );
        // redirige vers la liste des pilotes
        return $this->redirectToRoute('gestion-pilotes-liste');
    }
}
