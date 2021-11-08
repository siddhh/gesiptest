<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Service;
use App\Entity\Composant\Annuaire;
use App\Form\ServiceType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ServiceController extends AbstractController
{

    private $passwordEncoder;
    private $mailer;

    public function __construct(
        UserPasswordEncoderInterface $passwordEncoder,
        MailerInterface $mailer
    ) {
        $this->passwordEncoder = $passwordEncoder;
        $this->mailer = $mailer;
    }

    /**
     * @Route("/gestion/services", name="gestion-services-liste")
     */
    public function index(): Response
    {
        return $this->render('gestion/services/liste.html.twig');
    }

    /**
     * @Route("/gestion/services/creation", name="gestion-services-creation")
     */

    public function creationService(Request $request, UrlGeneratorInterface $router): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        $response = new Response();

        if ($form->isSubmitted() && $form->isValid()) {
            $motdepasse = Service::generationMotdepasse();
            $motdepasseCrypte = $this->passwordEncoder->encodePassword(
                $service,
                $motdepasse
            );
            $service->setMotdepasse($motdepasseCrypte);
            $service->setResetMotdepasse(true);
            $em = $this->getDoctrine()->getManager();
            $em->persist($service);
            $em->flush();

            $serviceLabel = $service->getLabel();
            $lienModificationMotdepasse = $this->getParameter('base_url') . $router->generate('connexion');
            $emailMessage = (new TemplatedEmail())
                ->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')))
                ->to(new Address($service->getEmail(), $serviceLabel))
                ->priority(Email::PRIORITY_HIGH)
                ->subject("GESIP Vos identifiants de connexion")
                ->textTemplate('emails/creationService.text.twig')
                ->htmlTemplate('emails/creationService.html.twig')
                ->context([
                        'serviceLabel'                  => $serviceLabel,
                        'motdepasse'                    => $motdepasse,
                        'lienModificationMotdepasse'    => $lienModificationMotdepasse
                    ]);

            $this->mailer->send($emailMessage);

            $this->addFlash(
                'success',
                "Le service {$service->getLabel()} a bien été créé."
            );
            // redirige vers la liste des services
            return $this->redirectToRoute('gestion-services-liste');
        }

        $response = $this->render('gestion/services/creation.html.twig', [
            'form' => $form->createView()
        ]);
        return $response;
    }

    /**
     * @Route("/gestion/services/{service}/modifier", name="gestion-services-modification")
     * Permet de modifier un service existant
     */
    public function modifierService(Request $request, Service $service): Response
    {
        // génère le formulaire à partir des données du service
        $serviceEstUsurpateur = $service->getEstRoleUsurpateur();
        $form = $this->createForm(ServiceType::class, $service);
        // récupère les paramètres fournis par le user
        $form->handleRequest($request);
        // si valide, on persiste l'état du service en base de données
        if ($form->isSubmitted() && $form->isValid()) {
            $service = $form->getData();
            if (!$this->isGranted(Service::ROLE_ADMIN) && $serviceEstUsurpateur) {
                $service->setEstRoleUsurpateur(true);
            }
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($service);
            $entityManager->flush();
            $this->addFlash(
                'success',
                "Le service {$service->getLabel()} a bien été modifié."
            );
            // redirige vers la liste des services
            return $this->redirectToRoute('gestion-services-liste');
        }
        // Retourne la page web
        return $this->render('gestion/services/modification.html.twig', [
            'form'      => $form->createView(),
            'service'   => $service,
            'roleAdmin' => Service::ROLE_ADMIN
        ]);
    }

    /**
     * @Route("/gestion/services/{service}/supprimer", name="gestion-services-suppression")
     * Supprime le service
     */
    public function suppression(Service $service, UserInterface $user)
    {
        if ($user->getId() === $service->getId()) {
            $this->addFlash(
                'danger',
                "Vous ne pouvez pas supprimer votre propre service."
            );
        } elseif (in_array(Service::ROLE_ADMIN, $service->getRoles()) && ! in_array(Service::ROLE_ADMIN, $user->getRoles())) {
            $this->addFlash(
                'danger',
                "Vous ne pouvez pas supprimer un service disposant du rôle Administrateur."
            );
        } else {
            // Affecte la date de suppression
            $dateSuppression = new \DateTime();
            $service->setSupprimeLe($dateSuppression);
            $em = $this->getDoctrine()->getManager();
            $em->persist($service);

            // Supprime de facon douce tous les annuaires comportant ce service
            $annuairesASupprimer = $this->getDoctrine()->getRepository(Annuaire::class)->findBy([
                'supprimeLe' => null,
                'service' => $service,
            ]);
            foreach ($annuairesASupprimer as $annuaire) {
                $annuaire->setSupprimeLe($dateSuppression);
            }

            // Persiste les modifications
            $em->flush();

            // Flash de la suppression de service
            $this->addFlash(
                'success',
                "Le service {$service->getLabel()} a bien été supprimé."
            );
        }

        // redirige vers la liste des services
        return $this->redirectToRoute('gestion-services-liste');
    }

    /**
     * @Route("/gestion/services/{service}/composants_csv", name="gestion-services-composants_csv")
     * Fournit sous forme de fichier CSV la liste des composants attachés à un service
     */
    public function listeComposantsCSV(Service $service)
    {
        $filename = $service->getLabel() . ' - composants.csv';

        $fileContent = "Composant;Mission\n";
        $listeComposants = $this->getDoctrine()->getRepository(Annuaire::class)->composantsDuService($service);
        foreach ($listeComposants as $annuaire) {
            $fileContent .= $annuaire->getComposant()->getLabel() . ";" . $annuaire->getMission()->getLabel() . "\n";
        }

        $response = new Response($fileContent);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }

    /**
     * @Route("/gestion/services/{service}/composants_impression", name="gestion-services-composants_impression")
     * Imprime la liste des composants attachés à un service
     */
    public function listeComposantsImpression(Request $request, Service $service): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);
        $listeAnnuaires = $this->getDoctrine()->getRepository(Annuaire::class)->composantsDuService($service);
        return $this->render('gestion/services/liste-composants-impression.html.twig', [
            'form'      => $form->createView(),
            'service'   => $service,
            'listeAnnuaires'=> $listeAnnuaires
        ]);
    }
}
