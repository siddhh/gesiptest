<?php

namespace App\Repository;

use App\Entity\Sollicitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Sollicitation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sollicitation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sollicitation[]    findAll()
 * @method Sollicitation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SollicitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sollicitation::class);
    }

    // /**
    //  * @return Sollicitation[] Returns an array of Sollicitation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Sollicitation
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
