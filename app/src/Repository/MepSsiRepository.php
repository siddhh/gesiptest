<?php

namespace App\Repository;

use App\Entity\MepSsi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MepSsi|null find($id, $lockMode = null, $lockVersion = null)
 * @method MepSsi|null findOneBy(array $criteria, array $orderBy = null)
 * @method MepSsi[]    findAll()
 * @method MepSsi[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MepSsiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MepSsi::class);
    }

    /**
     * Permet de lister les MEP SSI par prévus entre deux dates
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listeMepSsiPeriode(\DateTime $periodeDebut, \DateTime $periodeFin): array
    {
        $query = $this->createQueryBuilder('m')
            ->addSelect('e', 'd', 'c', 's', 'cc')
            ->join('m.equipe', 'e')
            ->join('m.statut', 's')
            ->join('m.composants', 'c')
            ->leftJoin('m.demandesInterventions', 'd')
            ->join('d.composant', 'cc')
            ->where('s.label IN (:statut)')->setParameter('statut', ['PROJET', 'CONFIRME'])
            ->andWhere('m.mepDebut <= :fin AND m.mepFin >= :debut')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin)
            ;

        // On lance la requête et on renvoi la réponse
        return $query->getQuery()->getResult();
    }

    /**
     * Liste des MEP SSI par mois
     * @param \DateTime $date
     * @return MepSsi[]
     */
    public function listeParMois(\DateTime $date): array
    {
        // On crée nos périodes de date en fonction de la date passée en paramètre
        $tz = new \DateTimeZone('Europe/Paris');
        $dateDebut = (clone $date)->modify('first day of this month')->setTime(0, 0, 0)->setTimezone($tz);
        $dateFin = (clone $date)->modify('last day of this month')->setTime(23, 59, 59, 59)->setTimezone($tz);

        // On effectue notre requête
        $qb = $this->createQueryBuilder('m')
            ->addSelect(['sm', 'e', 'p', 'c'])
            ->andWhere('m.mes BETWEEN (:dateDebut) AND (:dateFin)
                      OR (m.lep IS NOT NULL AND m.lep BETWEEN (:dateDebut) AND (:dateFin))
                      OR (m.mepDebut IS NOT NULL AND m.mepFin IS NOT NULL AND m.mepDebut < (:dateFin) AND m.mepFin > (:dateDebut))
                      OR (m.mepDebut IS NULL AND m.mepFin BETWEEN (:dateDebut) AND (:dateFin))
                      OR (m.mepFin IS NULL AND m.mepDebut BETWEEN (:dateDebut) AND (:dateFin))
                      ')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->join('m.statut', 'sm')
            ->join('m.equipe', 'e')
            ->leftJoin('m.pilotes', 'p')
            ->leftJoin('m.composants', 'c')
            ->orderBy('m.id')
        ;
        return $qb->getQuery()->getResult();
    }

    /*
     * Retourne les Meps pour les statistiques (état global)
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @return DemandeIntervention[]
     */
    public function listeMepsLien(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m', 'COALESCE(m.mepDebut, m.mes) AS mepDate')
            ->andWhere('COALESCE(m.mepDebut, m.mes) BETWEEN :debut AND :fin')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin)
            ->orderBy('mepDate', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les MEP SSI pour la vue inter-applicative
     * @param int $composantId[]
     * @return MepSsi[]
     */
    public function listePourVueInterApplicative(array $composantId): array
    {
        $qb = $this->createQueryBuilder('m')
            ->addSelect('c', 'd')
            ->where('m.mes >= :ceJour')
            ->setParameter('ceJour', (new \DateTime())->setTime(0, 0, 0, 0))
            ->join('m.composants', 'c')
            ->andWhere('c.id IN (:composants)')
            ->setParameter('composants', $composantId)
            ->leftJoin('c.domaine', 'd')
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * Fonction permettant de restituer les MEP SSI par composants.
     * @param array $composants
     * @param string $order
     * @return array
     */
    public function restitutionMepSsi(array $composants, string $order = 'DESC') : array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'COALESCE(m.mepDebut, m.mes) AS mepDate')
            ->where('COALESCE(m.mepDebut, m.mes) >= :ceJour')
            ->setParameter('ceJour', (new \DateTime())->setTime(0, 0, 0, 0))
            ->join('m.composants', 'c')
            ->andWhere('c.id IN (:composants)')
            ->setParameter('composants', $composants)
            ->orderBy('mepDate', $order)
            ->getQuery()
            ->getResult();
    }
}
