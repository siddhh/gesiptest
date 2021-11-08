<?php

namespace App\Command\Cron;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Shapecode\Bundle\CronBundle\Annotation\CronJob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Entity\Meteo\Publication;
use App\Entity\Meteo\Evenement;
use App\Entity\Meteo\Composant;
use App\Entity\Composant as GesipComposant;

/**
 * @CronJob("0 4 * * 5")
 * Sera exécuté chaque vendredi à 4h
 */
class PublicationMeteo extends Command
{
    /** @var string */
    protected static $defaultName = 'gesip:cron:meteo:publication';

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
            ->setDescription('[CRON] Permet la publication automatique des tableaux de bord Météo de la semaine écoulée (du jeudi au mercredi).')
        ;
    }

    /**
     * Définit l'éxécution de la commande
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tz = new \DateTimeZone('Europe/Paris');
        // on recherche le dernier mercredi (fin de la période à publier)
        $periodeFin = new \DateTime("now", $tz);
        $periodeFin->modify("last Wednesday");
        $periodeFin->setTime(23, 59, 59);

        // on recherche le jeudi précédent ce mercredi (début de la période à publier)
        $periodeDebut = clone $periodeFin;
        $periodeDebut->modify("-6 days");
        $periodeDebut->setTime(0, 0, 0);

        $em = $this->em;
        if (! $em->getRepository(Publication::class)->periodeEstPubliee($periodeDebut, $periodeFin)) {
            // on créé la période
            $publication = new Publication();
            $publication->setPeriodeDebut($periodeDebut);
            $publication->setPeriodeFin($periodeFin);
            $em->persist($publication);

            // on créé la météo des composants pour cette période
            $listeIdComposant = $em->getRepository(Evenement::class)->listeComposantsPeriode($periodeDebut, $periodeFin);
            $listeMeteoComposants = $em->getRepository(GesipComposant::class)->indicesMeteoComposants($listeIdComposant, $periodeDebut);
            foreach ($listeMeteoComposants as $meteoComposant) {
                $composant = new Composant();
                $composant->setPeriodeDebut($periodeDebut);
                $composant->setPeriodeFin($periodeFin);
                $composant->setComposant($em->getReference(GesipComposant::class, $meteoComposant['id']));
                $composant->setMeteo($meteoComposant['indice']);
                $composant->setDisponibilite($meteoComposant['disponibilite']);
                $em->persist($composant);
            }

            $em->flush();
        }
        return 0;
    }
}
