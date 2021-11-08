<?php

namespace App\Repository\Demande;

use App\Entity\DemandeIntervention;
use App\Entity\Demande\HistoriqueStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HistoriqueStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoriqueStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method HistoriqueStatus[]    findAll()
 * @method HistoriqueStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoriqueStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueStatus::class);
    }

    /**
     * Retourne la liste des lignes d'historique qui correspondent à la demande et l'état demandés.
     *  Si aucun état fourni, la méthode retourne l'historique complet pour la demande.
     *  Le résultat est trié par date décroissante
     * @param DemandeIntervention $demandeIntervention
     * @param array $status
     * @return array
     */
    public function getHistoriqueParEtat(DemandeIntervention $demandeIntervention, array $status = []): array
    {
        $qb = $this->createQueryBuilder('hs')
            ->where('hs.demande = :demandeIntervention')
            ->setParameter('demandeIntervention', $demandeIntervention)
            ->orderBy('hs.majLe', 'DESC');
        if (!empty($status)) {
            $qb->andWhere('hs.status IN (:status)')
                ->setParameter('status', $status);
        }
        return $qb->getQuery()->getResult();
    }
}
