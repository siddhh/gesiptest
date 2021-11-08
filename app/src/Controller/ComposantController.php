<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\Composant;
use App\Form\ComposantType;
use App\Entity\References\Mission;
use App\Form\RechercheComposantType;
use App\Utils\ExtraComposantHistory;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class ComposantController extends AbstractController
{
    /** @var EntityManagerInterface  */
    private $em;
    /** @var MailerInterface */
    private $mailer;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface $em
     * @param MailerInterface $mailer
     */
    public function __construct(EntityManagerInterface $em, MailerInterface $mailer)
    {
        $this->em = $em;
        $this->mailer = $mailer;
    }

    /**
     * @Route("/gestion/composants", name="gestion-composants-liste")
     */
    public function index(Request $request): Response
    {
        $form = $this->createForm(RechercheComposantType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('ajax-composant-listing');
        }
        return $this->render('gestion/composants/recherche.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/gestion/composants/creation", name="gestion-composants-creation")
     */
    public function creation(Request $request): Response
    {
        // ajout du composant
        $doctrine = $this->getDoctrine();
        $composant = new Composant();
        $form = $this->createForm(ComposantType::class, $composant);
        $form->handleRequest($request);
        $isSubmitted = $form->isSubmitted();
        if ($isSubmitted && $form->isValid()) {
            // Démarre une transaction
            $em = $doctrine->getManager();
            try {
                // Persistance du composant (les annuaires, et plages horaires associés sont automatiquement persistés)
                $composant = $form->getData();
                $em->persist($composant);
                $dureePlageUtilisateur = 0;
                foreach ($composant->getPlagesUtilisateur() as $plagesUtilisateur) {
                    $plagesUtilisateur->setComposant($composant);
                    $dureePlageUtilisateur += $plagesUtilisateur->getTempsTotalEnMinutes();
                }
                $composant->setDureePlageUtilisateur($dureePlageUtilisateur);
                foreach ($composant->getAnnuaire() as $annuaire) {
                    $annuaire->setComposant($composant);
                }
                foreach ($composant->getComposantsImpactes() as $composantsImpacte) {
                    $composant->removeComposantsImpacte($composantsImpacte);
                    $composant->addComposantsImpacte($composantsImpacte);
                }
                foreach ($composant->getImpactesParComposants() as $impactesParComposant) {
                    $composant->removeImpactesParComposant($impactesParComposant);
                    $composant->addImpactesParComposant($impactesParComposant);
                }
                // Si le composant s'impacte lui-meme il faut le rajouter dans les composants impactés
                if ($form->get('impacteLuiMeme')->getData()) {
                    $composant->addComposantsImpacte($composant);
                }
                // Enregistre en base de données le nouveau composant
                $em->flush();
                // envoi du mail
                $this->envoyerMail($composant);
                // Cloture la transaction
                $this->addFlash(
                    'success',
                    "Le composant {$composant->getLabel()} a bien été créé."
                );
                return $this->redirectToRoute('gestion-composants-liste');
            } catch (\Throwable $ex) {
                // Annulation de la transaction
                $this->addFlash(
                    'error',
                    "Une erreur est survenue lors de l'ajout du composant."
                );
            }
        }
        // Génération de la page de création d'un composant
        $services = $doctrine->getRepository(Service::class)->listeTousServices();
        $missions = $doctrine->getRepository(Mission::class)->listeToutesMissions();
        $composants = $this->getDoctrine()->getRepository(Composant::class)->listeComposants();
        return $this->render('gestion/composants/_form.html.twig', [
            'composant'     => $composant,
            'composants'    => $composants,
            'missions'      => $missions,
            'services'      => $services,
            'form'          => $form->createView(),
            'isSubmitted'   => $isSubmitted
        ]);
    }

     /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param Address[] $destinataireAdresses
     * @param array $templateContext
     */
    private function envoyerMail(Composant $composant)
    {
        $serviceConnecte = $this->getUser();
        $labelComposant = $composant->getLabel();
        $emailMessage = (new TemplatedEmail());
        $emailMessage->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        $emailMessage->addTo(new Address($serviceConnecte->getEmail(), $serviceConnecte->getLabel()));
        foreach ([$composant->getPilote(), $composant->getPiloteSuppleant()] as $pilote) {
            if (!empty($pilote)) {
                $emailMessage->addTo($pilote->getAddressObj());
            }
        }
        $emailMessage->subject("Création du composant $labelComposant - Gesip");
        $emailMessage->htmlTemplate('emails\composants\creation.html.twig');
        $emailMessage->textTemplate('emails\composants\creation.text.twig');
        $emailMessage->context([
            'composant' => $composant,
        ]);
        $this->mailer->send($emailMessage);
    }

    /**
     * @Route("/gestion/composants/{composant}/modifier", name="gestion-composants-modifier")
     * @Entity("composant", expr="repository.findAvecServicesAnnuaires(composant)")
     */
    public function modifierComposant(Request $request, Composant $composant): Response
    {
        // génère le formulaire à partir des données du composant
        $doctrine = $this->getDoctrine();
        $em = $doctrine->getManager();
        $form = $this->createForm(ComposantType::class, $composant);
        // désarchive un composant si nécessaire
        $allowUnarchiving = !is_null($composant->getArchiveLe())
            && empty($em->getRepository(Composant::class)->libelleComposantDejaUtilise(['label' => $composant->getLabel()]));
        if (!is_null($request->request->get('unarchive')) && $allowUnarchiving) {
            $composant->setArchiveLe(null);
            $em->flush();
            $this->addFlash(
                'success',
                "Le composant {$composant->getLabel()} a été désarchivé avec succès."
            );
            return $this->redirectToRoute('gestion-composants-liste');
        }
        // Initialise un object capable de détecter les modifications d'un composant dans le but de les journaliser
        $fch = new ExtraComposantHistory($em, $request, $this->getUser());
        $fch->setInitialComposant($composant);
        // Récupération des collections initiales
        $initialPlagesUtilisateur = [];
        foreach ($composant->getPlagesUtilisateur() as $plagesUtilisateur) {
            $initialPlagesUtilisateur[] = $plagesUtilisateur;
        }
        $initialAnnuaires = [];
        foreach ($composant->getAnnuaire() as $annuaire) {
            $initialAnnuaires[] = $annuaire;
        }
        $initialImpactesParComposants = [];
        foreach ($composant->getImpactesParComposants() as $comp) {
            $initialImpactesParComposants[] = $comp;
        }
        $initialComposantsImpactes = [];
        foreach ($composant->getComposantsImpactes() as $comp) {
            $initialComposantsImpactes[] = $comp;
        }
        // récupère les paramètres
        $form->handleRequest($request);
        $isSubmitted = $form->isSubmitted();
        if ($isSubmitted && $form->isValid()) {
            // Démarre une transaction
            try {
                // Ajoute les nouvelles plages utilisateur
                $dureePlageUtilisateur = 0;
                foreach ($composant->getPlagesUtilisateur() as $plagesUtilisateur) {
                    $plagesUtilisateur->setComposant($composant);
                    $dureePlageUtilisateur += $plagesUtilisateur->getTempsTotalEnMinutes();
                }
                // Suppression des plages utilisateurs supprimées
                foreach ($initialPlagesUtilisateur as $plagesUtilisateur) {
                    if (false === $composant->getPlagesUtilisateur()->contains($plagesUtilisateur)) {
                        $dureePlageUtilisateur -= $plagesUtilisateur->getTempsTotalEnMinutes();
                        $em->remove($plagesUtilisateur);
                    }
                }
                $composant->setDureePlageUtilisateur($dureePlageUtilisateur);
                // Ajoute les nouvelles entrées d'annuaire
                foreach ($composant->getAnnuaire() as $annuaire) {
                    $annuaire->setComposant($composant);
                }
                // Ajoute les nouvelles entrées dans les flux sortants
                foreach ($composant->getImpactesParComposants() as $comp) {
                    $comp->addComposantsImpacte($composant);
                }
                foreach ($initialImpactesParComposants as $comp) {
                    if (null !== $comp->getArchiveLe()) {
                        $composant->addImpactesParComposant($comp);
                    } elseif (false === $composant->getImpactesParComposants()->contains($comp)) {
                        $comp->removeComposantsImpacte($composant);
                    }
                }
                // Ajoute les nouvelles entrées dans les flux entrants
                foreach ($initialComposantsImpactes as $comp) {
                    if (null !== $comp->getArchiveLe()) {
                        $composant->addComposantsImpacte($comp);
                    }
                }
                // archive si nécessaire
                $archiving = false;
                if ($form->get('estArchive')->getData() && !$composant->estArchive()) {
                    $composant->setArchiveLe(new \DateTime());
                    $archiving = true;
                }
                // enregistre les modifications effectuées sur les composants à partir des états initiaux / finaux.
                $fch->setFinalComposant($composant);
                $fch->writeHistory();
                // enregistre les modifications en base de données, valide les modifications et redirige l'utilisateur
                $em->flush();
                $this->addFlash(
                    'success',
                    $archiving ? "L'archivage du composant {$composant->getLabel()} a été pris en compte."
                        : "Le composant {$composant->getLabel()} a été modifié avec succès."
                );
                return $this->redirectToRoute('gestion-composants-liste');
            } catch (\Throwable $ex) {
                // Annulation de la transaction et renvoi d'un message d'erreur.
                $this->addFlash(
                    'error',
                    "Une erreur est survenue lors de l'ajout du composant."
                );
            }
        }
        // Génération de la page de modification d'un composant
        $services = $this->getDoctrine()->getRepository(Service::class)->listeTousServices();
        $missions = $this->getDoctrine()->getRepository(Mission::class)->listeToutesMissions();
        $composants = $this->getDoctrine()->getRepository(Composant::class)->listeComposants();
        return $this->render('gestion/composants/_formModification.html.twig', [
            'composant'         => $composant,
            'composants'        => $composants,
            'missions'          => $missions,
            'services'          => $services,
            'form'              => $form->createView(),
            'isSubmitted'       => true,
            'flagModification'  => true,
            'allowUnarchiving'  => $allowUnarchiving,
        ]);
    }
}
