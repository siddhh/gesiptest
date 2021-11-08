<?php

namespace App\Repository\Documentation;

use App\Entity\Documentation\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Document|null find($id, $lockMode = null, $lockVersion = null)
 * @method Document|null findOneBy(array $criteria, array $orderBy = null)
 * @method Document[]    findAll()
 * @method Document[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Récupère un document sans ses fichiers supprimés
     * @param int $id
     * @return ?Document
     */
    public function getActiveDocument(int $id): ?Document
    {
        return $this->createQueryBuilder('d')
            ->addSelect('f')
            ->leftJoin('d.fichiers', 'f', Join::WITH, 'f.supprimeLe IS NULL')
            ->where('d.id = :id AND d.supprimeLe IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
