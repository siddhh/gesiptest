<?php

namespace App\Repository\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DemandeReferentielFlux|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandeReferentielFlux|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandeReferentielFlux[]    findAll()
 * @method DemandeReferentielFlux[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeReferentielFluxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeReferentielFlux::class);
    }

    /**
     * Permet de récupérer la liste des demandes avec un filtrage possible par service
     * @param Service|null $service
     * @param Composant|null $composantSource
     * @param Composant|null $composantTarget
     * @param string|null $typeDemande
     * @param bool $enAttenteUniquement
     * @return array
     */
    public function findAllDemandes(
        Service $service = null,
        Composant $composantSource = null,
        Composant $composantTarget = null,
        string $typeDemande = null,
        bool $enAttenteUniquement = true
    ): array {
        // On met en forme un début de requête
        $query = $this->createQueryBuilder('d')
            ->addSelect(['s', 'cs', 'ct'])
            ->join('d.serviceDemandeur', 's')
            ->join('d.composantSource', 'cs')
            ->join('d.composantTarget', 'ct');

        // Si un service est passé en paramètre, on ajoute un filtre dans la requête
        if ($service !== null) {
            $query->andWhere('d.serviceDemandeur = :service')
                ->setParameter('service', $service);
        }

        // Si un composant source est passé en paramètre, on ajoute un filtre dans la requête
        if ($composantSource !== null) {
            $query->andWhere('d.composantSource = :composantSource')
                ->setParameter('composantSource', $composantSource);
        }

        // Si un composant target est passé en paramètre, on ajoute un filtre dans la requête
        if ($composantTarget !== null) {
            $query->andWhere('d.composantTarget = :composantTarget')
                ->setParameter('composantTarget', $composantTarget);
        }

        // Si un type est passé en paramètre, on ajoute un filtre dans la requête
        if ($typeDemande !== null) {
            $query->andWhere('d.type = :type')
                ->setParameter('type', $typeDemande);
        }

        // Si nous voulons uniquement les demandes en attente
        if ($enAttenteUniquement) {
            $query->andWhere('d.accepteLe is null')
                ->andWhere('d.refuseLe is null')
                ->andWhere('d.annuleLe is null');
        }

        // On exécute la requête et l'on renvoi son résultat
        return $query->getQuery()->getResult();
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
            ->join('d.composantSource', 'c')
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
    * Recherche les demande de flux en attente
    * @return DemandeReferentielFlux[]
    */
    public function rechercheDemandesEnAttente(array $filters = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect(['cs', 'ct', 'cse', 'csp', 'cte', 'ctp'])
            ->join('d.composantSource', 'cs')
            ->join('d.composantTarget', 'ct')
            ->leftJoin('cs.equipe', 'cse')
            ->leftJoin('cs.pilote', 'csp')
            ->leftJoin('ct.equipe', 'cte')
            ->leftJoin('ct.pilote', 'ctp')
            ->where('d.accepteLe is NULL')
            ->andWhere('d.refuseLe is NULL')
            ->andWhere('d.annuleLe is NULL')
            ->orderBy('d.ajouteLe', 'DESC');

        if (!empty($filters['equipe'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('cs.equipe', ':equipe'),
                $qb->expr()->eq('ct.equipe', ':equipe')
            ));
            $qb->setParameter('equipe', $filters['equipe']);
        }
        if (!empty($filters['pilote'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('cs.pilote', ':pilote'),
                $qb->expr()->eq('cs.piloteSuppleant', ':pilote'),
                $qb->expr()->eq('ct.pilote', ':pilote'),
                $qb->expr()->eq('ct.piloteSuppleant', ':pilote')
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
}
