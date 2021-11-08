<?php

namespace App\Command\Cron;

use App\Entity\DemandeIntervention;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatSaisirRealise;
use Doctrine\ORM\EntityManagerInterface;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @CronJob("* * * * *")
 * Sera exécuté toutes les minutes
 */
class DemandeChangementEtatAutoCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'gesip:cron:changement-etat-auto';

    /** @var EntityManagerInterface  */
    private $em;

    /**
     * Constructeur de la commande.
     * Permet notamment de récupérer dépendances
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    /**
     * Configure la commande
     */
    protected function configure()
    {
        $this
            ->setDescription('Permet de changer l\'état des demandes automatiquement lorsque l\'intervention humaine n\'est pas requise.')
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
        // initialisation
        $io = new SymfonyStyle($input, $output);
        $debugMode = $input->getOption('dry-run');
        $io->title('Lancement procédure changement automatique d\'état des demandes d\'intervention...');
        $io->note('Date courante (UTC): ' . (new \DateTime())->format('d/m/Y H:i'));
        if ($debugMode) {
            $io->caution('Mode DEBUG activé !');
        }

        // listing des demandes d'intervention dans les états "Accordee" et "En cours d'intervention"
        $demandeInterventions = $this->em->getRepository(DemandeIntervention::class)->listeDemandesChangementAuto();
        if (count($demandeInterventions) > 0) {
            $rows = [];
            foreach ($demandeInterventions as $demandeIntervention) {
                $me = $demandeIntervention->getmachineEtat();
                $demandeId = $demandeIntervention->getId();
                $demandeNumero = $demandeIntervention->getNumero();
                $dateDebut = $demandeIntervention->getDateDebut()->format('d/m/Y H:i');
                $dateFinMax = $demandeIntervention->getDateFinMax()->format('d/m/Y H:i');
                $etatCourant = $demandeIntervention->getStatus();
                $nouvelEtatClasse = null;
                try {
                    if ($etatCourant == EtatAccordee::class) {
                        $nouvelEtatClasse = EtatInterventionEnCours::class;
                    } elseif ($etatCourant == EtatInterventionEnCours::class) {
                        $nouvelEtatClasse = EtatSaisirRealise::class;
                    } else {
                        throw new \Exception('Etat de demande non pris en charge !');
                    }
                    if (!$debugMode) {
                        $me->changerEtat($nouvelEtatClasse);
                    }
                    $rows[] = [
                        $demandeId,
                        $demandeNumero,
                        $dateDebut,
                        $dateFinMax,
                        str_replace('App\\Workflow\\Etats\\', '', $etatCourant),
                        str_replace('App\\Workflow\\Etats\\', '', $nouvelEtatClasse),
                        'OK' . ($debugMode ? ' (debug)' : '')
                    ];
                } catch (\Exception $ex) {
                    $rows[] = [
                        $demandeId,
                        $demandeNumero,
                        $dateDebut,
                        $dateFinMax,
                        str_replace('App\\Workflow\\Etats\\', '', $etatCourant),
                        str_replace('App\\Workflow\\Etats\\', '', $nouvelEtatClasse),
                        'FAIL: ' . $ex->getMessage(),
                    ];
                }
            }

            // Répercute les modifications en base de données
            $this->em->flush();

            // Affiche un résumé des opérations entreprises et du résultat
            $table = new Table($output);
            $table->setHeaders([
                'Id',
                'Numéro',
                'Date début (UTC)',
                'Date de fin maxi (UTC)',
                'Etat précédent',
                'Nouvel Etat',
                'Résultat',
            ]);
            $table->setRows($rows);
            $table->render();
        } else {
            $io->writeLn('Aucune demande trouvée devant être modifiée.');
        }

        // Termine la commande par un succès
        return 0;
    }
}
