<?php

namespace App\Controller\Ajax\Fiabilisation;

use App\Entity\Service;
use App\Entity\Sollicitation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class SollicitationController extends AbstractController
{
    /**
     * @Route(
     *      "/ajax/fiabilisation/sollicitation/relancer",
     *      methods={"POST"},
     *      name="ajax-fiabilisation-sollicitation-relancer"
     * )
     */
    public function relancerServices(Request $request, MailerInterface $mailer): JsonResponse
    {
        // On récupère le service connecté et la date de la requête
        /** @var Service $serviceCourant */
        $serviceCourant = $this->getUser();
        $date = new \DateTime();

        // Récupération de l'Entity Manager et des paramètres passés dans la requête
        $em = $this->getDoctrine()->getManager();
        $servicesIds = $request->get('servicesIds', []);
        $copyMail = $request->get('copyMail');

        // Si nous souhaitons relancer au moins un service
        if (count($servicesIds) > 0) {
            // On récupère les services par rapport aux ids envoyés par le serveur et qui ne sont pas supprimé
            $services = $em->getRepository(Service::class)->findBy([ 'id' => $servicesIds, 'supprimeLe' => null ]);

            // On parcourt cette liste de service
            /** @var Service $service */
            foreach ($services as $service) {
                // On change la date de dernière solicitation
                $service->setDateDerniereSollicitationAffichage($date);

                // On crée une sollicitation pour garder l'historique
                $sollicitation = new Sollicitation();
                $sollicitation->setServiceSollicite($service);
                $sollicitation->setSolliciteLe($date);
                $sollicitation->setSollicitePar($serviceCourant);
                $em->persist($sollicitation);

                // On envoi le mail au service
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')))
                    ->to(new Address($service->getEmail(), $service->getLabel()))
                    ->addBcc(new Address('bureau.si2a-dme-soae@dgfip.finances.gouv.fr', 'DGFiP SI-2A DME SOAE'))
                    ->priority(Email::PRIORITY_HIGH)
                    ->subject("[GESIP] Validation / Mise a jour de vos données dans GESIP")
                    ->textTemplate('emails/fiabilisation/sollicitation/sollicitation.text.twig')
                    ->htmlTemplate('emails/fiabilisation/sollicitation/sollicitation.html.twig');

                // Si l'utilisateur a demandé une copie de l'email
                if ($copyMail) {
                    $emailMessage->addBcc(new Address($serviceCourant->getEmail(), $serviceCourant->getLabel()));
                }

                // On envoi l'email
                $mailer->send($emailMessage);
            }

            $em->flush();
        }

        // Envoi du message de succès
        $this->addFlash(
            'success',
            "L’envoi du mail « Solliciter les services » a été effectué avec succès au(x) service(s) sélectionné(s)."
        );
        return new JsonResponse([
            'status' => 'success'
        ]);
    }
}
