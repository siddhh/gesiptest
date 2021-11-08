<?php

namespace App\Repository\Composant;

use App\Entity\Composant\PlageUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PlageUtilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlageUtilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlageUtilisateur[]    findAll()
 * @method PlageUtilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlageUtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlageUtilisateur::class);
    }

    /**
     * Récupère toutes les valeurs sous forme d'un tableau avec comme clé, la clé primaire de l'entrée.
     * @return array
     */
    public function findAllInArray(): array
    {
        return $this->createQueryBuilder("p", "p.id")
            ->join("p.composant", "c")
            ->getQuery()->getResult();
    }
}
