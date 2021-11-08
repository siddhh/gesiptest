<?php

namespace App\Repository\Meteo;

use App\Entity\Meteo\Composant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Composant|null find($id, $lockMode = null, $lockVersion = null)
 * @method Composant|null findOneBy(array $criteria, array $orderBy = null)
 * @method Composant[]    findAll()
 * @method Composant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComposantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Composant::class);
    }

    /**
     * Fonction permettant de récupérer la météo des composants pour une période donnée.
     *
     * @param \DateTime $periodeDebut
     *
     * @return array
     */
    public function listeMeteoComposantsPeriode(\DateTime $periodeDebut) : array
    {
        return $this->createQueryBuilder('c')
            ->where('c.periodeDebut = :debut')
            ->setParameter('debut', $periodeDebut)
            ->getQuery()
            ->getResult();
    }
}
