<?php

namespace App\Repository\Meteo;

use App\Entity\Meteo\Publication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Publication|null find($id, $lockMode = null, $lockVersion = null)
 * @method Publication|null findOneBy(array $criteria, array $orderBy = null)
 * @method Publication[]    findAll()
 * @method Publication[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /**
     * Fonction permettant de récupérer les périodes publiées par rapport à un mois et année donné.
     *
     * @param int $annee
     * @param int $mois
     *
     * @return array
     */
    public function periodesPubliees(int $annee, int $mois) : array
    {
        // On récupère la date de début et de fin du mois passé en paramètre
        $dateDebut = \DateTime::createFromFormat('Y-m-d', $annee . '-' . str_pad($mois, 2, "0", STR_PAD_LEFT) . '-01')
            ->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
        $dateFin = (clone $dateDebut)->modify('last day of this month');

        // On renvoi les infos en base de données
        return $this->createQueryBuilder('p')
            ->andWhere('(p.periodeDebut >= :debut AND p.periodeDebut <= :fin)')
            ->orWhere('(p.periodeFin >= :debut AND p.periodeFin <= :fin)')
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->getQuery()
            ->getResult();
    }

    /**
     * Fonction permettant de savoir si la période passée est publiée ou non.
     *
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     *
     * @return bool
     */
    public function periodeEstPubliee(\DateTime $periodeDebut, \DateTime $periodeFin) : bool
    {
        // On reste les temps des date (au cas où!)
        $periodeDebut->setTime(0, 0, 0);
        $periodeFin->setTime(23, 59, 59);

        // Si une valeur existe en base pour la période passée en paramètre
        return $this->getEntityManager()->getRepository(Publication::class)->createQueryBuilder('p')
                ->andWhere('p.periodeDebut = :periodeDebut')
                ->setParameter('periodeDebut', $periodeDebut)
                ->andWhere('p.periodeFin = :periodeFin')
                ->setParameter('periodeFin', $periodeFin)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult() !== null;
    }

    /**
     * Fonction permettant de récupérer les jours publiées d'un mois et d'une année précise.
     *
     * @param int $annee
     * @param int $mois
     *
     * @return array
     */
    public function joursPeriodesPubliees(int $annee, int $mois) : array
    {
        // On récupère les périodes déjà publiées du mois
        $periodesPubliees = $this->periodesPubliees($annee, $mois);
        $joursPeriodesPubliees = [];

        // Avec les périodes récupérées, on récupère les jours associés
        /** @var Publication $periodePubliee */
        foreach ($periodesPubliees as $periodePubliee) {
            // On clone notre date de début
            $debut = clone $periodePubliee->getPeriodeDebut();
            // Et on boucle tant que la date de début n'est pas supérieure strictement à la date de fin
            do {
                // On rempli le tableau de jours
                $joursPeriodesPubliees[] = $debut->format('Y-m-d');
                // On ajoute +1 jour à notre date de début
                $debut->add(new \DateInterval('P1D'));
            } while ($debut <= $periodePubliee->getPeriodeFin());
        }

        // On retourne les jours
        return $joursPeriodesPubliees;
    }
}
