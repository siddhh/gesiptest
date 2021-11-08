<?php

namespace App\Repository\Demande;

use App\Entity\DemandeIntervention;
use App\Entity\Demande\ImpactReel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ImpactReel|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImpactReel|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImpactReel[]    findAll()
 * @method ImpactReel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImpactReelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImpactReel::class);
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
        $query = $this->createQueryBuilder('ir')
            ->select(['ir', 's', 'd', 'n', 'c', 'irc'])
            ->join('ir.saisieRealise', 's')
            ->join('ir.nature', 'n')
            ->join('s.demande', 'd')
            ->leftJoin('d.composant', 'c')
            ->leftJoin('ir.composants', 'irc')
            ->andWhere('s.demande = :demande')
                ->setParameter('demande', $demande)
            ->andWhere('ir.dateDebut <= :periodeFin AND ir.dateFin >= :periodeDebut')
                ->setParameter('periodeDebut', $periodeDebut)
                ->setParameter('periodeFin', $periodeFin);

        // On lance la requête et on renvoi la réponse
        return $query->getQuery()->getResult();
    }
}
