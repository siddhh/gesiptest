<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\TestNotificationType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class TestNotificationController extends AbstractController
{

    private $mailer;

    public function __construct(
        MailerInterface $mailer
    ) {
        $this->mailer = $mailer;
    }

    /**
    * @Route("/test/envoi/notification", name="test-envoi-notification")
    */

    public function envoiNotificationTest(Request $request): Response
    {

        $form = $this->createForm(TestNotificationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $balps = $form->get('listeBalps')->getData();
            $this->envoyerMail($balps);
            $balpsString = implode(' / ', $balps);
            $this->addFlash(
                'success',
                "La notification a été envoyée à $balpsString."
            );
            // redirige vers la liste des pilotes
            return $this->redirectToRoute('test-envoi-notification');
        }

        // On renvoi la vue rendue par twig
        return $this->render('gestion/test-notification/test_notification.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param Address[] $destinataireAdresses
     * @param array $templateContext
     */
    private function envoyerMail(array $balps)
    {
        $serviceConnecte = $this->getUser();
        $emailMessage = (new TemplatedEmail());
        $emailMessage->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($balps as $balp) {
            if (!empty($balp)) {
                $emailMessage->addTo($balp);
            }
        }
        $emailMessage->subject("TEST NOTIFICATION GESIP");
        $emailMessage->htmlTemplate('emails\test-notification\test_notification.html.twig');
        $emailMessage->textTemplate('emails\test-notification\test_notification.text.twig');
        $this->mailer->send($emailMessage);
    }
}
