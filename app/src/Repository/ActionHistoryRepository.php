<?php

namespace App\Repository;

use App\Entity\ActionHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ActionHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActionHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActionHistory[]    findAll()
 * @method ActionHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionHistory::class);
    }

    /**
     * Récupération des modifications effectuées en fonction d'une classe d'entitée, qu'une action ainsi qu'une date
     * @param array|null $classeEntite
     * @param  array|null $action
     * @param \DateTime|null $date
     * @param array|null $orderBy
     * @return QueryBuilder
     */
    public function listeActionsParEntite(array $classes = null, array $actions = null, \DateTime $date = null, array $orderBy = null): array
    {
        $query = $this->createQueryBuilder('a');
        // Filtre la liste des type d'objets à partir des classes fournis
        if (!empty($classes)) {
            $query->andWhere('a.objetClasse IN (:classes)')->setParameter('classes', $classes);
        }
        // Filtre la liste des actions à partir du type d'action
        if (!empty($action)) {
            $query->andWhere('a.action IN (:actions)')->setParameter('actions', $actions);
        }
        // Filtre la liste des actions à partir d'un jour précis
        if ($date !== null) {
            $debut = clone($date);
            $debut->setTime(0, 0, 0);
            $fin = clone($date);
            $fin->setTime(23, 59, 59);
            $query->andWhere('a.actionDate >= :du');
            $query->setParameter('du', $debut);
            $query->andWhere('a.actionDate <= :au');
            $query->setParameter('au', $fin);
        }
        // Affecte les tris
        if (null === $orderBy) {
            $orderBy = [
                'actionDate'    => 'asc',
                'id'            => 'asc',
            ];
        }
        foreach ($orderBy as $fieldName => $direction) {
            $query->addOrderBy('a.' . $fieldName, strtoupper($direction));
        }
        return $query->getQuery()->getResult();
    }
}
