<?php

namespace App\Command\Cron;

use App\Entity\DemandeIntervention;
use App\Workflow\Actions\ActionAnnulerCommande;
use App\Workflow\Etats\EtatRenvoyee;
use Doctrine\ORM\EntityManagerInterface;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @CronJob("30 2 * * *")
 * Sera exécuté 1 fois par jour à 2h30 du matin
 */
class DemandeAnnulationRenvoyerAutoCommand extends Command
{

    /** @var string */
    protected static $defaultName = 'gesip:cron:demande:annulation-renvoyer-auto';

    /** @var EntityManagerInterface  */
    private $em;
    /** @var MailerInterface */
    private $mailer;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em, MailerInterface $mailer)
    {
        parent::__construct();
        $this->em = $em;
        $this->mailer = $mailer;
    }

    /**
     * Configure la commande
     */
    protected function configure()
    {
        $this
            ->setDescription('[CRON] Permet d\'annuler automatiquement les demandes renvoyées passées et ou dont le délai de renvoi a expiré.')
            ->addOption('--dry-run', '-d', InputOption::VALUE_NONE, 'Si ce mode est activé, les demandes consernées par une annulation seront affichées, aucune modification sera effectuée.')
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

        // Initialisation
        $io = new SymfonyStyle($input, $output);
        $debugMode = $input->getOption('dry-run');
        $io->title('Lancement annulation automatique des demandes renvoyées non traitées'
            . ' (en attente de correction depuis trop longtemps)...');
        $io->note('Date courante (UTC): ' . (new \DateTime())->format('d/m/Y H:i'));
        if ($debugMode) {
            $io->caution('Mode DEBUG activé !');
        }

        // 30 jours après la dernière modification
        $dateLimiteCorrection = (new \DateTime('now'))->sub(new \DateInterval('P30D'))->setTime(0, 0, 0);
        // Liste les demandes renvoyées qu'il faudrait annuler
        $demandeAAnnulers = $this->em->getRepository(DemandeIntervention::class)
                ->createQueryBuilder('d')
                ->where('d.status IN (:status)')
                ->setParameter('status', [EtatRenvoyee::class])
                ->andWhere('d.majLe < :dateLimiteCorrection')
                ->setParameter('dateLimiteCorrection', $dateLimiteCorrection)
                ->orderBy('d.dateDebut', 'ASC')
                ->getQuery()
                ->getResult();
            ;

        // Toutes les demandes correspondantes doivent être annulées
        $rows = [];
        foreach ($demandeAAnnulers as $demande) {
            // Récupération de la demande et du motif
            $commentaire = 'Fermeture automatique, la demande est restée trop longtemps en attente de correction depuis le ' . $demande->getMajLe()->format('d/m/Y') . '.';
            // Affichage de la demande d'intervention
            $rows[] = [
                $demande->getId(),
                $demande->getNumero(),
                $demande->getStatusLibelle(),
                $demande->getDateDebut()->format('d/m/Y'),
                $demande->getMajLe()->format('d/m/Y'),
                $commentaire,
            ];
            // Lancement de l'action annuler spécifique (changement d'état + envoi de mail)
            if (!$debugMode) {
                $request = Request::create('/', 'POST', [
                    'envoyerMail' => true,
                    'commentaire' => $commentaire,
                ]);
                $me = $demande->getmachineEtat();
                $me->setEntityManager($this->em);
                $me->setMailer($this->mailer);
                $me->executerAction(ActionAnnulerCommande::class, $request);
            }
        }
        $this->em->flush();

        // Affichage de la table des interventions concernées
        if (!empty($rows)) {
            $table = new Table($output);
            $table->setHeaders(['Id', 'Numéro', 'status', 'Date début', 'Date mise à jour', 'Motif']);
            $table->setRows($rows);
            $table->render();
        } else {
            $output->writeLn('Aucune demande concernée.');
        }

        return 0;
    }
}
