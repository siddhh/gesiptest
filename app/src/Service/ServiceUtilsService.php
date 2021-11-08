<?php

namespace App\Service;

use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ServiceUtilsService
{

    private $passwordEncoder;
    private $em;
    private $logger;
    private $mailer;
    private $router;
    private $container;

    /**
     * Récupère le UserPasswordEncoder pour crypter les mots de passe
     */
    public function __construct(
        EntityManagerInterface $em,
        UserPasswordEncoderInterface $passwordEncoder,
        LoggerInterface $logger,
        MailerInterface $mailer,
        UrlGeneratorInterface $router,
        ContainerInterface $container
    ) {
        $this->em = $em;
        $this->passwordEncoder = $passwordEncoder;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->router = $router;
        $this->container = $container;
    }

    /**
     * Génère un nouveau mot de passe pour le service et envoi un mail à ce dernier
     *  retourne true si l'opération a été correctement effectuée, sinon false
     *  (si false est retourné, le mot de passe du service reste inchangé)
     */
    public function motdepasseReinitialise(Service $service): bool
    {
        try {
            // Génère un nouveau mot de passe pour ce service
            $motdepasse = Service::generationMotdepasse();
            $motdepasseCrypte = $this->passwordEncoder->encodePassword(
                $service,
                $motdepasse
            );
            $service->setMotdepasse($motdepasseCrypte);
            $service->setResetMotdepasse(true);
            // Envoie un mail de réinitialisation au service concerné
            $serviceLabel = $service->getLabel();
            $lienModificationMotdepasse = $this->container->getParameter('base_url') . $this->router->generate('connexion');
            $emailMessage = (new TemplatedEmail())
                ->from(new Address($this->container->getParameter('noreply_mail'), $this->container->getParameter('noreply_mail_label')))
                ->to(new Address($service->getEmail(), $serviceLabel))
                ->priority(Email::PRIORITY_HIGH)
                ->subject("GESIP Oubli du mot de passe")
                ->textTemplate('emails/motdepasseReinitialisation.text.twig')
                ->htmlTemplate('emails/motdepasseReinitialisation.html.twig')
                ->context([
                        'serviceLabel'                  => $serviceLabel,
                        'motdepasse'                    => $motdepasse,
                        'lienModificationMotdepasse'    => $lienModificationMotdepasse
                    ]);

            $this->mailer->send($emailMessage);
            // Enregistre / valide l'état du service si tout est ok
            $this->em->persist($service);
            $this->em->flush();
        } catch (\Exception $ex) {
            $this->logger->error("La réinitialisation d\'un mot de passe a échoué !"
                . PHP_EOL . (string)$ex);
            return false;
        }
        return true;
    }
}
