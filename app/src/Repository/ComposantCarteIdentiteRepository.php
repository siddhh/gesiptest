<?php

namespace App\Repository;

use App\Entity\ComposantCarteIdentite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ComposantCarteIdentite|null find($id, $lockMode = null, $lockVersion = null)
 * @method ComposantCarteIdentite|null findOneBy(array $criteria, array $orderBy = null)
 * @method ComposantCarteIdentite[]    findAll()
 * @method ComposantCarteIdentite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComposantCarteIdentiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComposantCarteIdentite::class);
    }

    /**
    * recherche si libellé composant déjà utilisé avec casse différente
    * @return ComposantCarteIdentite[]
    */
    public function libelleComposantDejaUtilise(string $label): array
    {
        return $this->createQueryBuilder('c')
            ->where('UPPER(c.label) = :val')
            ->setParameter('val', strtoupper($label))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }
}
