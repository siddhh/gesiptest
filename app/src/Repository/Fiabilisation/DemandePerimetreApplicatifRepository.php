<?php

namespace App\Repository\Fiabilisation;

use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DemandePerimetreApplicatif|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandePerimetreApplicatif|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandePerimetreApplicatif[]    findAll()
 * @method DemandePerimetreApplicatif[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandePerimetreApplicatifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandePerimetreApplicatif::class);
    }

    /**
     * Permet de compter le nombre de demande en attente
     * (si null en paramètre, on ne filtre pas les demandes par service demandeur)
     * @param Service|null $parServiceDemandeur
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function nombreDemandesEnAttente(?Service $parServiceDemandeur = null): int
    {
        // On commence à créer la requête de comptage
        $qb = $this->createQueryBuilder('d')
            ->select(['COUNT(d.id)'])
            ->join('d.composant', 'c')
            ->andWhere('d.accepteLe is null')
            ->andWhere('d.refuseLe is null')
            ->andWhere('d.annuleLe is null');

        // Si l'on doit filtrer par service demandeur ou service pilote
        if ($parServiceDemandeur !== null) {
            $qb->andWhere('d.serviceDemandeur = :serviceDemandeur OR c.equipe = :serviceDemandeur');
            $qb->setParameter('serviceDemandeur', $parServiceDemandeur);
        }

        // On lance la requête et on renvoi la réponse
        return $qb->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche les demandes en attente
     * @return DemandePerimetreApplicatif[]
     */
    public function rechercheDemandesEnAttente(array $filters = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect(['c', 'm', 'ce', 'cp'])
            ->join('d.composant', 'c')
            ->join('d.mission', 'm')
            ->leftJoin('c.equipe', 'ce')
            ->leftJoin('c.pilote', 'cp')
            ->where('d.accepteLe is NULL')
            ->andWhere('d.refuseLe is NULL')
            ->andWhere('d.annuleLe is NULL')
            ->orderBy('d.ajouteLe', 'DESC');

        if (!empty($filters['equipe'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('c.equipe', ':equipe'),
                $qb->expr()->eq('c.equipe', ':equipe')
            ));
            $qb->setParameter('equipe', $filters['equipe']);
        }
        if (!empty($filters['pilote'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('c.pilote', ':pilote'),
                $qb->expr()->eq('c.piloteSuppleant', ':pilote'),
                $qb->expr()->eq('c.pilote', ':pilote'),
                $qb->expr()->eq('c.piloteSuppleant', ':pilote')
            ));
            $qb->setParameter('pilote', $filters['pilote']);
        }
        if (!empty($filters['type'])) {
            $qb->andWhere('d.type = :type');
            $qb->setParameter('type', $filters['type']);
        }
        if (!empty($filters['serviceDemandeur'])) {
            $qb->andWhere('d.serviceDemandeur = :serviceDemandeur');
            $qb->setParameter('serviceDemandeur', $filters['serviceDemandeur']->getId());
        }
        if (!empty($filters['ajouteLe'])) {
            $dateString = $filters['ajouteLe']->format('Y-m-d');
            $qb->andWhere('d.ajouteLe BETWEEN :start AND :end');
            $qb->setParameter('start', $dateString . ' 00:00:00');
            $qb->setParameter('end', $dateString . ' 23:59:59');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Liste les demandes en cours de modification du périmètre applicatif d'un service
     * @return DemandePerimetreApplicatif[]
     */
    public function listePerimetreApplicatifEncoursService(Service $service): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT d, c, m
            FROM App\Entity\Fiabilisation\DemandePerimetreApplicatif d
            LEFT JOIN d.composant c
            LEFT JOIN d.mission m
            WHERE d.serviceDemandeur = :service
            AND d.accepteLe IS NULL
            AND d.refuseLe IS NULL
            AND d.annuleLe IS NULL
            ORDER by d.id ASC'
        );
        $query->setParameter('service', $service);

        return $query->getResult();
    }
}
