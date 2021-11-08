<?php

namespace App\Repository;

use App\Entity\Pilote;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Pilote|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pilote|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pilote[]    findAll()
 * @method Pilote[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PiloteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pilote::class);
    }

    /**
    * recherche si balp pilote déjà utilisé avec casse différente
    * @return Pilote[]
    */
    public function balpDejaUtilise($champs)
    {
        return $this->createQueryBuilder('s')
            ->where('UPPER(s.balp) = :val')
            ->setParameter('val', strtoupper($champs['balp']))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Recherche et met en forme pour la recherche du champ Search Multi Select (par Nom / Prénom)
     * @return array[]
     */
    public function multiSelectSearchByNomPrenom($labelSearch): array
    {
        // On se prépare
        $resultats = [];
        $labelSearch = '%' . str_replace(['_', '%'], ['\\_', '\\%'], mb_strtolower($labelSearch)) . '%';

        // On lance notre requête
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT PARTIAL p.{ id, nom, prenom }
            FROM App\Entity\Pilote p
            WHERE (
                    lower(p.nom) LIKE :filtre
                    OR
                    lower(p.prenom) LIKE :filtre
                ) AND p.supprimeLe IS null
            ORDER by p.nom ASC'
        )
        ->setParameter('filtre', $labelSearch)
        ->getResult();

        // On ajoute une entête pour l'affichage des résultats du champ
        $resultats[] = [
            'nom' => 'Nom',
            'prenom' => 'Prénom'
        ];

        // On met en forme
        foreach ($query as $pilote) {
            $resultats[] = [
                'id' => $pilote->getId(),
                'nom' => $pilote->getNom(),
                'prenom' => $pilote->getPrenom(),
                'nomCompletCourt' => $pilote->getNomCompletCourt()
            ];
        }

        // On renvoi les résultats
        return $resultats;
    }

    /**
     * Forme une requête permettant de lister les pilotes par un filtre particulier passé en paramètre
     *
     * @param string $filtre
     * @return Query
     */
    public function listePilotesFiltre(string $filtre = null): Query
    {
        $entityManager = $this->getEntityManager();
        $filtre = '%' . mb_strtolower($filtre) . '%';

        $query = $entityManager->createQuery(
            'SELECT
                PARTIAL p.{
                    id,
                    nom,
                    prenom,
                    balp
                },
                PARTIAL e.{
                    id,
                    label
                }
            FROM App\Entity\Pilote p
            JOIN p.equipe e
            WHERE (
                    lower(p.nom) LIKE :filtre
                    OR
                    lower(p.prenom) LIKE :filtre
                    OR
                    lower(p.balp) LIKE :filtre
                    OR
                    lower(e.label) LIKE :filtre
                ) AND p.supprimeLe IS null
            ORDER by p.nom ASC'
        );
        $query->setParameter('filtre', $filtre);

        return $query;
    }

    /**
     * Permet de créer une requête afin de récupérer les pilotes et pilotes suppléants en fonction d'un tableau de composants
     * @param array $ids
     * @return QueryBuilder
     */
    public function listePilotesParComposants(array $ids): QueryBuilder
    {
        $query = $this->createQueryBuilder('p')
            ->distinct()
            ->addSelect(['p', 'c', 'sc'])
            ->leftJoin('p.composants', 'c')
            ->leftJoin('p.suppleantComposants', 'sc')
            ->where('c.id IN (:composantsIds)')
            ->orWhere('sc.id IN (:composantsIds)');

        $query->setParameter('composantsIds', $ids);

        return $query;
    }

    /**
     * Récupère toutes les valeurs sous forme d'un tableau avec comme clé, la clé primaire de l'entrée.
     * @return array
     */
    public function findAllInArray(): array
    {
        return $this->createQueryBuilder("p", "p.id")->getQuery()->getArrayResult();
    }

    /**
     * Listing des Pilotes (titulaires uniquement si $avecSuppleants est faux) pour les écrans de restitutions.
     * @param bool $avecSuppleants
     * @return array
     */
    public function restitutionListing(bool $avecSuppleants = false) : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT p.id as id, p.prenom, p.nom, CONCAT(p.prenom, \' \', p.nom) as label, COUNT(c) as nbComposants
            FROM App\Entity\Pilote p
            INNER JOIN App\Entity\Composant c WITH c.pilote = p.id' .  ($avecSuppleants ? ' OR c.piloteSuppleant = p.id' : '') .
            ' WHERE c.archiveLe IS NULL AND
                p.supprimeLe IS NULL
            GROUP BY id, label
            ORDER BY p.nom, p.prenom ASC'
        );
        return $query->getResult();
    }
}
