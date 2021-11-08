<?php

namespace App\Command\Cron;

use App\Entity\DemandeIntervention;
use App\Workflow\DestinatairesCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;

/**
 * @CronJob("0 1 * * *")
 * Sera exécuté 1 fois par jour à 1h00 du matin
 */
class DemandeEmailRetardCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'gesip:cron:demandes:emails-retard';

    /** @var EntityManagerInterface  */
    private $em;

    /** @var MailerInterface */
    private $mailer;

    /** @var string */
    private $noreply_mail;

    /** @var string */
    private $noreply_mail_label;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface $em
     * @param MailerInterface $mailer
     * @param string $noreply_mail
     * @param string $noreply_mail_label
     */
    public function __construct(EntityManagerInterface $em, MailerInterface $mailer, string $noreply_mail, string $noreply_mail_label)
    {
        parent::__construct();
        $this->em = $em;
        $this->mailer = $mailer;
        $this->noreply_mail = $noreply_mail;
        $this->noreply_mail_label = $noreply_mail_label;
    }

    /**
     * Configure la commande
     */
    protected function configure()
    {
        $this
            ->setDescription('[CRON] Permet d\'envoyer un mail aux Equipes/Pilotes/Administrateur des demandes en retard de réponse.')
            ->addOption('--with-interaction', '-i', InputOption::VALUE_NONE, 'Lorsque cette option n\'est pas utilisée, tous les pilotes sont sélectionnés et la date d\'envoi + 11j donne celles en retard, sinon vous serez invité à saisir ces données')
            ->addOption('--dry-run', '-d', InputOption::VALUE_NONE, 'Nous n\'enverrons pas réellement les emails mais afficherons un tableau de debug')
        ;
    }

    /**
     * Défini l'éxécution de la commande
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * On défini quelques variables utiles
         */
        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        // Mode débug
        $debugMode = $input->getOption('dry-run');

        // Mode interactif
        $interactifMode = $input->getOption('with-interaction');

        // Déclaration de la date pour le script
        $dateDesRetards = (new \DateTime())->setTime(0, 0, 0);

        /**
         * Si on est en mode interactif, on peut saisir la date de retard souhaitée
         */
        if ($interactifMode) {
            $newDate = $io->ask('Souhaitez-vous définir une date de retard particulière ? (par défaut: ' . $dateDesRetards->format('d/m/Y H:i:s') . ')');
            if ($newDate) {
                $dateDesRetards = \DateTime::createFromFormat('d/m/Y H:i:s', $newDate . '00:00:00');
                $dateDesRetards->setTimezone(new \DateTimeZone('Europe/Paris'));
            }
        }

        /**
         * On récupère la liste des demandes d'intervention dont nous aurons besoin par la suite
        */
        $demandesInterventions = $this->em->getRepository(DemandeIntervention::class)->getDemandeInterventionsPourCalculRetardDecisionDme($dateDesRetards);
        foreach ($demandesInterventions as $demandeIntervention) {
            $tableauDemandes[] = [
                $demandeIntervention->getId(),
                $demandeIntervention->getNumero(),
                $demandeIntervention->getComposant()->getLabel(),
                $demandeIntervention->getDateLimiteDecisionDme()->format('c'),
                $demandeIntervention->getDateDebut()->format('c'),
            ];
        }
        if (count($demandesInterventions) > 0) {
            $io->table([ 'ID', 'Numero', 'Composant', 'Date limite', 'Date de début' ], $tableauDemandes);
        } else {
            echo('Pas de Demandes d\'intervention trouvées');
            return 0;
        }

        /**
         * Si nous sommes en mode interactif, on peut indiquer les demandes d'intervention
         * à notifier
         */
        $onlyDemandesIds = [];
        if ($interactifMode) {
            do {
                $tmp = $io->ask('Donnez l\'ID de la Demande d\'Intervention à notifier (ne rien taper pour notifier toutes les demandes)');
                if ($tmp !== null) {
                    $onlyDemandesIds[] = $tmp;
                }
            } while ($tmp !== null);

            // On demander à l'utilisateur de confirmer que l'on souhaite bien envoyer les emails de notifications.
            if (count($onlyDemandesIds) === 0 && !$io->confirm("Confirmez-vous l'envoi des notifications pour toutes les demandes en base de données ?", false)) {
                $io->error("Annulation effectuée");
                return 1;
            }
        }

        /**
         * On récupère la liste des demandes à notifier.
         */
        $demandesInterventionsValides = [];
        foreach ($demandesInterventions as $demandeIntervention) {
            if (!$interactifMode ||
                $interactifMode && count($onlyDemandesIds) === 0 ||
                $interactifMode && in_array($demandeIntervention->getId(), $onlyDemandesIds)
            ) {
                $demandesInterventionsValides[] = $demandeIntervention;
            }
        }

        /**
         * Pour chaque demande d'intervention en retard, on envoie un mail
         */
        $nbEmails = 0;
        foreach ($demandesInterventionsValides as $demandeIntervention) {
            // Construction de la liste des destinataires
            $destinatairesCollection = new DestinatairesCollection(
                $demandeIntervention,
                [
                    DestinatairesCollection::OPTION_ADMINS,
                    DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
                ],
                $this->em
            );
            // Construction du mail et envoi
            $emailMessage = (new TemplatedEmail())->from(new Address($this->noreply_mail, $this->noreply_mail_label));
            foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
                $emailMessage->addTo($destinataire);
            }
            $composantLabel = $demandeIntervention->getComposant()->getLabel();
            $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
            $emailMessage
                ->subject("{$composantLabel} [GESIP:DELAI DE REPONSE DEPASSE] Intervention programmée {$demandeDateString}")
                ->textTemplate('emails/demandes/retards.text.twig')
                ->htmlTemplate('emails/demandes/retards.html.twig')
                ->context([
                    'demandeIntervention'   => $demandeIntervention,
                    'equipe'                => $demandeIntervention->getComposant()->getEquipe(),
                ]);
            if (!$debugMode) {
                $this->mailer->send($emailMessage);
            }
            $nbEmails++;
        }

        if ($debugMode) {
            $io->warning("Mode débug actif, aucun mails n'a été envoyés !");
        }
        $io->success($nbEmails . ' mails ont été envoyés avec succès !');
        return 0;
    }
}
