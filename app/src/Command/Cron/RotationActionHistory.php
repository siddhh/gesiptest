<?php

namespace App\Command\Cron;

use App\Entity\ActionHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @CronJob("30 4 * * *")
 * Sera exécuté 1 fois par jour à 4h30 du matin
 */
class RotationActionHistory extends Command
{
    /** @var string */
    protected static $defaultName = 'gesip:cron:action-history:rotation';

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
            ->setDescription('[CRON] Supprime les entrées ActionHistory trop vieilles.')
            ->addOption('--with-interaction', '-i', InputOption::VALUE_NONE, 'Lorsque cette option est activée, la commande demandera confirmation avant de lancer la suppression.')
            ->addOption('--date', '-D', InputOption::VALUE_REQUIRED, 'Permet de choisir une autre date pivot pour la suppression des entrées (format Y-m-d H:i:s).')
            ->addOption('--dry-run', '-d', InputOption::VALUE_NONE, 'Aucune valeur sera supprimée.')
        ;
    }

    /**
     * Définit l'éxécution de la commande
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupération du mode dry-run
        $dryrunMode = $input->getOption('dry-run');
        if ($dryrunMode) {
            $output->writeln('<fg=magenta>Mode dry-run activé (aucune ligne sera supprimée).</>');
        }

        // Récupération de la date pivot à partir de laquelle il faut garder / supprimer les entrées ActionHistory
        $maxDate = null;
        $dtzParis = new \DateTimeZone('Europe/Paris');
        $sDate = $input->getOption('date');
        if (null === $sDate) {
            $maxDate = (new \DateTime('now', $dtzParis))->sub(new \DateInterval('P4M'));
        } else {
            $maxDate = \DateTime::createFromFormat('Y-m-d H:i:s', $sDate, $dtzParis);
        }

        // Affiche la date prévue pour la suppression (avec demande de confirmation en mode interactif)
        $message = "Toutes les entrées ActionHistory postérieures au {$maxDate->format('Y-m-d H:i:s')} (Europe/Paris) vont être supprimées.";
        if ($input->getOption('with-interaction')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("<question>{$message} Souhaitez-vous continuer ?</question>", false, '/^(y|o)/i');
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<error>Suppression abandonnée (aucune données supprimées).</error>');
                return 1;
            }
        } else {
            $output->writeln("<comment>{$message}</comment>");
        }

        // Effectue la suppression des entrées ActionHistory trop vieilles en base de données
        $dtzUtc = new \DateTimeZone('utc');
        $maxDate->setTimeZone($dtzUtc);
        if ($dryrunMode) {
            $deletedRows = $this->em->getRepository(ActionHistory::class)->createQueryBuilder('ah')
                ->select('COUNT(ah.id)')
                ->where('ah.actionDate < :maxdate')->setParameter('maxdate', $maxDate)
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            $deletedRows = $this->em->createQueryBuilder()
                ->delete(ActionHistory::class, 'ah')
                ->where('ah.actionDate < :maxdate')->setParameter('maxdate', $maxDate)
                ->getQuery()
                ->execute();
        }

        // Affiche le résultat et quitte
        if ($dryrunMode) {
            $output->writeln("<fg=magenta>{$deletedRows} lignes auraient été supprimées (mode dry-run activé).</>");
        } else {
            $output->writeln("<info>{$deletedRows} lignes supprimées.</info>");
        }
        return 0;
    }
}
