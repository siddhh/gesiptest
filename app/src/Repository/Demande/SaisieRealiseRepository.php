<?php

namespace App\Repository\Demande;

use App\Entity\Demande\SaisieRealise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SaisieRealise|null find($id, $lockMode = null, $lockVersion = null)
 * @method SaisieRealise|null findOneBy(array $criteria, array $orderBy = null)
 * @method SaisieRealise[]    findAll()
 * @method SaisieRealise[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SaisieRealiseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaisieRealise::class);
    }
}
