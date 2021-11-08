<?php

namespace App\Repository\References;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\References\Reference;

abstract class ReferenceRepository extends ServiceEntityRepository
{
    /** @var string */
    protected $entityClass = Reference::class;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, $this->entityClass);
    }

    /**
    * liste les références
    * @return Reference[]
    */
    public function liste(?bool $avecSupprime = false)
    {
        $queryBuilder = $this->createQueryBuilder('s');
        if (!$avecSupprime) {
            $queryBuilder = $queryBuilder->where('s.supprimeLe is NULL');
        }
        return $queryBuilder
            ->orderBy('LOWER(s.label)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
    * Retourne une réfèrence avec le même libellé si trouvée (comparaison non sensible à la casse)
    * @return Reference[]
    */
    public function trouveReferenceParLabel($champs)
    {
        return $this->createQueryBuilder('s')
            ->where('UPPER(s.label) = :val')
            ->andWhere('s.supprimeLe is NULL')
            ->setParameter('val', mb_strtoupper($champs['label']))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Récupère toutes les valeurs sous forme d'un tableau avec comme clé, la clé primaire de l'entrée.
     * @return array
     */
    public function findAllInArray(): array
    {
        return $this->createQueryBuilder("r", "r.id")->getQuery()->getArrayResult();
    }
}
