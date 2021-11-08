<?php

namespace App\Repository\Composant;

use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\Composant\Annuaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Annuaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Annuaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Annuaire[]    findAll()
 * @method Annuaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnnuaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annuaire::class);
    }

    /**
     * Recherche des composants rattachés à un service
     * @return array[]
     */
    public function composantsDuService(Service $service): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT
                PARTIAL a.{
                    id,
                    balf
                },
                PARTIAL m.{
                    id,
                    label
                },
                PARTIAL c.{
                    id,
                    label
                }
            FROM App\Entity\Composant\Annuaire a
            JOIN a.mission m
            JOIN a.composant c
            WHERE
                a.supprimeLe is null AND
                a.service = :service AND
                c.archiveLe is null
            '
        );
        $query->setParameter("service", $service);

        return $query->getResult();
    }

    /**
     * Recherche des composants rattachés à un composant
     * @param boolean $uniquementIntervenant
     * @return array[]
     */
    public function annuaireParComposants(Composant $composant, bool $uniquementIntervenant = false): array
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL a.{id, balf}, PARTIAL m.{id, label}, PARTIAL c.{id, label}')
            ->from(Annuaire::class, 'a')
            ->join('a.mission', 'm')
            ->join('a.composant', 'c')
            ->andWhere('a.supprimeLe IS NULL')
            ->andWhere('c.archiveLe IS NULL')
            ->andWhere('a.composant = :composant')
            ->setParameter('composant', $composant);

        if ($uniquementIntervenant) {
            $query->andWhere("m.label LIKE 'ES%' OR m.label LIKE 'EA%'
                OR m.label LIKE 'MOE%' OR m.label LIKE 'Exploitant%'");
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Récupère toutes les valeurs sous forme d'un tableau avec comme clé, la clé primaire de l'entrée.
     * @return array
     */
    public function findAllInArray(): array
    {
        return $this->createQueryBuilder("a", "a.id")
                ->addSelect(["m", "s", "c"])
                ->join("a.mission", "m")
                ->join("a.service", "s")
                ->join("a.composant", "c")
                ->getQuery()->getResult();
    }

    /**
     * Récupère toutes les missions par services et par composants
     * @return array
     */
    public function findAllMissionByServices(): array
    {
        $resultats = [];
        $query = $this->getEntityManager()->createQuery("
            SELECT
                s.id as serviceId,
                c.id as composantId,
                m.id as missionId,
                m.label as missionLabel
           FROM App\Entity\Composant\Annuaire a
           LEFT JOIN a.mission m
           LEFT JOIN a.service s
           LEFT JOIN a.composant c
        ")->getArrayResult();

        foreach ($query as $infos) {
            $resultats[$infos['serviceId']][$infos['composantId']] = [
                'id' => $infos['missionId'],
                'label' => $infos['missionLabel']
            ];
        }

        return $resultats;
    }

    /**
     * Listing des composants et des missions pour un service donné
     * @param Service $service
     * @return array
     */
    public function composantsEtMissionParService(Service $service) : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT a, PARTIAL m.{id, label}, PARTIAL c.{id, label}
            FROM App\Entity\Composant\Annuaire a
            LEFT JOIN a.mission m
            LEFT JOIN a.composant c
            WHERE
                c.archiveLe IS NULL AND
                m.supprimeLe IS NULL AND
                a.service = :service AND
                a.id IN (
                    SELECT MIN(a2.id)
                    FROM App\Entity\Composant\Annuaire a2
                    WHERE a2.supprimeLe IS NULL AND a.service = :service
                    GROUP BY a2.service, a2.composant, a2.mission
                )
            ORDER BY c.label, m.label ASC'
        );
        $query->setParameter('service', $service);
        return $query->getResult();
    }

    /**
     * Forme une requête permettant de lister les annuaires associés à une balf donnée et à une liste de services.
     * (si balf null!)
     * @param string $balf
     * @param array $idsServices
     * @return array
     */
    public function rechercheParBalf(string $balf, array $idsServices = []): array
    {
        return $this->createQueryBuilder('a')
            ->select(['a', 's', 'm', 'c'])
            ->leftJoin('a.service', 's')
            ->leftJoin('a.mission', 'm')
            ->leftJoin('a.composant', 'c')
            ->where('a.balf LIKE :balf OR (a.balf IS NULL AND a.service IN (:idsServices))')
            ->setParameter('balf', '%' . mb_strtolower($balf) . '%')
            ->setParameter('idsServices', $idsServices)
            ->orderBy('c.label, s.label')
            ->andWhere('a.supprimeLe IS NULL AND s.supprimeLe IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Permet d'aller chercher en base de données les annuaires avec les relations service / mission / composant dont les
     * ids sont passés en paramètres.
     * @param array $ids
     * @return array
     */
    public function listingParIdsAvecRelations(array $ids): array
    {
        return $this->createQueryBuilder('a')
            ->select(['a', 's', 'm', 'c'])
            ->leftJoin('a.service', 's')
            ->leftJoin('a.mission', 'm')
            ->leftJoin('a.composant', 'c')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('c.label, s.label')
            ->getQuery()
            ->getResult();
    }
}
