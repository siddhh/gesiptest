<?php

namespace App\Command\Cron;

use App\Entity\Demande\ImpactReel;
use App\Entity\Demande\SaisieRealise;
use App\Entity\DemandeIntervention;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Actions\ActionSaisirRealise;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatInterventionEchouee;
use Doctrine\ORM\EntityManagerInterface;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @CronJob("0 2 * * *")
 * Sera exécuté 1 fois par jour à 2h00 du matin
 */
class DemandeFermetureAutoCommand extends Command
{
    /** Définit l'intervale de jours par défaut */
    const DEFAULT_DAYS = 'P7D';

    /** @var string */
    protected static $defaultName = 'gesip:cron:demande:fermeture-auto';

    /** @var EntityManagerInterface  */
    private $em;

    /** @var  */
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
            ->setDescription('[CRON] Permet de cloturer automatiquement les demandes en attente de saisie du réalisé pour lesquelles la date de fin maximale d\'intervention est dépassée.')
            ->addOption('--intervale', '-i', InputOption::VALUE_REQUIRED, 'Permet de changer l\'intervale de retard (au format DateInterval, par défaut P7D, 7 jours).')
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
        // Intervale et date
        $intervale = empty($input->getOption('intervale')) ? self::DEFAULT_DAYS : $input->getOption('intervale');

        // On récupère les demandes d'interventions correspondant au traitement à effectuées
        //  (en attente saisie réalisé + date de fin plus vieille que l'intervale)
        $dateAujourdhui = new \DateTime();
        $dateFinMaxi = $dateAujourdhui->sub(new \DateInterval($intervale));
        $demandes = $this->em->getRepository(DemandeIntervention::class)
            ->listeVieilleDemandeIntervention([EtatSaisirRealise::class], $dateFinMaxi);

        // Pour chaque demande on applique le traitement de cloture
        $rows = [];
        foreach ($demandes as $demande) {
            $rows[] = [
                $demande->getId(),
                $demande->getNumero(),
                $demande->getStatusLibelle(),
                $demande->getDateFinMax()->format('d/m/Y'),
            ];
            $this->cloturerDemande($demande);
        }

        // Affichage de la table des interventions concernées
        if (!empty($rows)) {
            $table = new Table($output);
            $table->setHeaders(['Id', 'Numéro', 'status', 'Date fin max']);
            $table->setRows($rows);
            $table->render();
        } else {
            $output->writeLn('Aucune demande concernée.');
        }

        return 0;
    }

    /**
     * Applique le traitement désiré sur chaque demande
     * @param DemandeIntervention $demandeIntervention
     * @return void
     */
    private function cloturerDemande(DemandeIntervention $demandeIntervention): void
    {
        $newStatus = EtatInterventionReussie::class;
        $machineEtat = $demandeIntervention->getMachineEtat();
        $saisies = $demandeIntervention->getSaisieRealises();
        // En fonction de si pas d'impact rééls (ou pas de saisie du réalisé)
        if (count($saisies) <= 0) {
            // Si pas d'impact réels, alors on bascule tous les impacts prévisionnels en impact réels.
            // D'abord, on crée l'objet SaisieRealise ...
            $saisieRealise = new SaisieRealise();
            $saisieRealise->setResultat(ActionSaisirRealise::RESULTAT_SUCCESS);
            $this->em->persist($saisieRealise);
            $demandeIntervention->addSaisieRealise($saisieRealise);

            // ...puis les objets ImpactReel.
            foreach ($demandeIntervention->getImpacts() as $impact) {
                $impactReel = new ImpactReel();
                $impactReel->setNumeroOrdre($impact->getNumeroOrdre());
                $impactReel->setNature($impact->getNature());
                $impactReel->setCommentaire($impact->getCommentaire());
                $impactReel->setDateDebut($impact->getDateDebut());
                $impactReel->setDateFin($impact->getDateFinMax());
                foreach ($impact->getComposants() as $composant) {
                    $impactReel->addComposant($composant);
                }
                $this->em->persist($impactReel);
                $saisieRealise->addImpactReel($impactReel);
            }
        } else {
            // on cherche si une saisie est echouée
            foreach ($saisies as $saisieRealise) {
                if ($saisieRealise->getResultat() == ActionSaisirRealise::RESULTAT_FAIL) {
                    $newStatus = EtatInterventionEchouee::class;
                    break;
                }
            }
        }
        // enfin on cloture la demande
        $machineEtat->changerEtat($newStatus);
        $this->em->flush();

        // si tout est ok, on envoie le mail correspondant
        $this->envoyerMail(
            $this->listerDestinataires($demandeIntervention),
            [
                'status'              => $newStatus == EtatInterventionReussie::class ? 'success' : 'fail',
                'demandeIntervention' => $demandeIntervention,
            ]
        );
    }

    /**
     * Récupère une liste de destinataires
     *
     * @param DemandeIntervention $demandeIntervention
     *
     * @return Address[]
     */
    private function listerDestinataires(DemandeIntervention $demandeIntervention): array
    {
        // On ajoute les destinataires (les mêmes que pour ActionSaisirRealise )
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_DEMANDEUR,
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_INTERVENANTS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
                DestinatairesCollection::OPTION_SERVICES_COMPOSANT,
                DestinatairesCollection::OPTION_SERVICES_IMPACTES,
            ],
            $this->em
        );
        return $destinatairesCollection->getDestinataires();
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     *
     * @param Address[] $destinataireAdresses
     * @param array     $templateContext
     */
    private function envoyerMail(array $destinataireAdresses, array $templateContext = [])
    {
        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->noreply_mail, $this->noreply_mail_label));
        foreach ($destinataireAdresses as $adresse) {
            $emailMessage->addTo($adresse);
        }
        $demandeIntervention = $templateContext['demandeIntervention'];
        $status = $templateContext['status'];
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
        ->subject("{$composantLabel} [GESIP:INTERVENTION " . ($status == 'success' ? "REALISEE AVEC SUCCES" : "EN ECHEC") . "] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/saisir-realise.text.twig')
            ->htmlTemplate('emails/demandes/workflow/saisir-realise.html.twig')
            ->context($templateContext);
        $this->mailer->send($emailMessage);
    }
}
