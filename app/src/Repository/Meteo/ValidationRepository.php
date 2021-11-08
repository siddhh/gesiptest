<?php

namespace App\Repository\Meteo;

use App\Entity\Meteo\Validation;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Validation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Validation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Validation[]    findAll()
 * @method Validation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Validation::class);
    }

    public function validationDateParExploitantPeriode(Service $exploitant, \DateTime $periodeDebut)
    {
        $periodeDebut = (clone $periodeDebut)->setTime(0, 0, 0);

        $validation = $this->createQueryBuilder('v')
            ->where('v.exploitant = :exploitant')->setParameter(':exploitant', $exploitant)
            ->andWhere('v.periodeDebut = :periodeDebut')->setParameter(':periodeDebut', $periodeDebut)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if (count($validation) > 0) {
            return $validation[0]->getAjouteLe();
        }

        return false;
    }

    public function validationsDateParPeriode(\DateTime $periodeDebut)
    {
        $periodeDebut = (clone $periodeDebut)->setTime(0, 0, 0);

        $validations = $this->createQueryBuilder('v')
            ->andWhere('v.periodeDebut = :periodeDebut')->setParameter(':periodeDebut', $periodeDebut)
            ->getQuery()
            ->getResult();

        return $validations;
    }
}
