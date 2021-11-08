<?php

namespace App\Repository\Demande;

use App\Entity\Demande\Impact;
use App\Entity\DemandeIntervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatTerminee;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatAnalyseEnCours;

/**
 * @method Impact|null find($id, $lockMode = null, $lockVersion = null)
 * @method Impact|null findOneBy(array $criteria, array $orderBy = null)
 * @method Impact[]    findAll()
 * @method Impact[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImpactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Impact::class);
    }

/**
     * Permet de lister les impacts prévus entre deux dates
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listeImpactsPeriode(\DateTime $periodeDebut, \DateTime $periodeFin): array
    {
        $query = $this->createQueryBuilder('i')
            ->addSelect('d', 'n', 'c')
            ->join('i.nature', 'n')
            ->join('i.demande', 'd')
            ->join('d.composant', 'c')
            ->where('d.status IN (:status)')->setParameter('status', [
                EtatAccordee::class,
                EtatConsultationEnCoursCdb::class,
                EtatConsultationEnCours::class,
                EtatAnalyseEnCours::class,
                EtatTerminee::class,
                EtatInterventionReussie::class,
                EtatInterventionEchouee::class
            ])
            ->andWhere('i.dateDebut <= :fin AND i.dateFinMax >= :debut')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin)
            ;

        // On lance la requête et on renvoi la réponse
        return $query->getQuery()->getResult();
    }

    /**
     * Permet de lister les impacts réels par demande
     *
     * @param DemandeIntervention $demande
     * @param \DateTime           $periodeDebut
     * @param \DateTime           $periodeFin
     *
     * @return array
     */
    public function impactsDemandePourTransferMeteo(DemandeIntervention $demande, \DateTime $periodeDebut, \DateTime $periodeFin): array
    {
        // On passe les dates en UTC
        $periodeDebut = (clone $periodeDebut)->setTimezone(new \DateTimeZone('utc'));
        $periodeFin = (clone $periodeFin)->setTimezone(new \DateTimeZone('utc'));

        // On prépare notre requête pour récupère les infos dans la base
        $query = $this->createQueryBuilder('i')
            ->select(['i', 'd', 'n', 'c', 'ic'])
            ->join('i.nature', 'n')
            ->join('i.demande', 'd')
            ->leftJoin('d.composant', 'c')
            ->leftJoin('i.composants', 'ic')
            ->andWhere('i.demande = :demande')
                ->setParameter('demande', $demande)
            ->andWhere('i.dateDebut <= :periodeFin AND i.dateFinMax >= :periodeDebut')
                ->setParameter('periodeDebut', $periodeDebut)
                ->setParameter('periodeFin', $periodeFin);

        // On lance la requête et on renvoi la réponse
        return $query->getQuery()->getResult();
    }
}
