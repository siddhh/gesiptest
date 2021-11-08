<?php

namespace App\Repository;

use App\Entity\Composant\Annuaire;
use App\Entity\Service;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

/**
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void
    {
        if (!$user instanceof Service) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newEncodedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    /**
     * liste de tous les services
     * @return Service[]
     */
    public function listeTousServices(): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT s
            FROM App\Entity\Service s
            WHERE s.supprimeLe is null
            ORDER by s.label ASC'
        );

        return $query->getResult();
    }

    /**
     * recherche d'un service par son libellé
     * @return Service[]
     */
    public function rechercheServiceParLabel($label): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT s
            FROM App\Entity\Service s
            WHERE s.label = :label
            ORDER by s.label ASC'
        )->setParameter('label', $label);

        return $query->getResult();
    }

    /**
     * Forme une requête permettant de lister les services par un filtre particulier passé en paramètre
     *
     * @param string $filtre
     * @return Query
     */
    public function listeServicesFiltre(string $filtre = null): Query
    {
        $entityManager = $this->getEntityManager();
        $filtre = '%' . mb_strtolower($filtre) . '%';

        $query = $entityManager->createQuery(
            'SELECT partial s.{
                id,
                label,
                email,
                estServiceExploitant,
                estBureauRattachement,
                estStructureRattachement,
                estPilotageDme
            }
            FROM App\Entity\Service s
            WHERE (lower(s.label) LIKE :filtre OR lower(s.email) LIKE :filtre)
            AND s.supprimeLe IS null
            ORDER by s.label ASC'
        );
        $query->setParameter('filtre', '%' . $filtre . '%');

        return $query;
    }

    /**
    * recherche si libellé service déjà utilisé avec casse différente
    * @return Service[]
    */
    public function libelleServiceDejaUtilise($champs)
    {
        return $this->createQueryBuilder('s')
            ->where('UPPER(s.label) = :val')
            ->setParameter('val', mb_strtoupper($champs['label']))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Récupère toutes les valeurs sous forme d'un tableau avec comme clé, la clé primaire de l'entrée.
     * @return array
     */
    public function findAllInArray(): array
    {
        return $this->createQueryBuilder("s", "s.id")->getQuery()->getArrayResult();
    }

    /**
     * Récupère toutes les dates distinctes des dernières sollicitation des services.
     * @return array
     */
    public function datesDernieresSollicitation(): array
    {
        $query = $this->createQueryBuilder('s')
            ->distinct()
            ->select('DATE_FORMAT(s.dateDerniereSollicitation, \'DD/MM/YYYY\') as date')
            ->addSelect('s.dateDerniereSollicitation')
            ->where('s.dateDerniereSollicitation IS NOT NULL')
            ->andWhere('s.dateDerniereSollicitation >= :aPartirDe')
            ->andWhere('s.supprimeLe IS NULL')
            ->setParameter('aPartirDe', (new \DateTime())->sub(new \DateInterval('P2Y'))->setTime(0, 0, 0))
            ->orderBy('s.dateDerniereSollicitation', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($query, 'date');
    }

    /**
     * Permet de rechercher un service pour la partie sollicitation
     * @param array|null $filters
     * @return Service[]
     */
    public function rechercheSollicitationServices(array $filters = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.supprimeLe IS NULL')
            ->leftJoin(Annuaire::class, 'a', Query\Expr\Join::WITH, 'a.service = s.id')
            ->leftJoin('a.composant', 'c')
            ->orderBy('s.dateDerniereSollicitation', 'ASC');

        if (!empty($filters['equipe'])) {
            $qb->andWhere('c.equipe = :equipe')
               ->setParameter('equipe', $filters['equipe']);
        }

        if (!empty($filters['mission'])) {
            $qb->andWhere('a.mission = :missionId')
               ->setParameter('missionId', $filters['mission']);
        }

        if (!empty($filters['service'])) {
            $qb->andWhere('s.id = :service')
                ->setParameter('service', $filters['service']);
        }

        if (!empty($filters['balf'])) {
            $qb->andWhere('LOWER(s.email) LIKE :balf')
                ->setParameter('balf', '%' . mb_strtolower($filters['balf']) . '%');
        }

        if (isset($filters['solliciteLe']) && $filters['solliciteLe'] !== null) {
            if ($filters['solliciteLe'] == 0) {
                $qb->andWhere('s.dateDerniereSollicitation IS NULL');
            } else {
                $dateString = \DateTime::createFromFormat('d/m/Y', $filters['solliciteLe'])->format('Y-m-d');
                $qb->andWhere('s.dateDerniereSollicitation BETWEEN :start AND :end')
                   ->setParameter('start', $dateString . ' 00:00:00')
                   ->setParameter('end', $dateString . ' 23:59:59');
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les services administrateurs
     * @param array|string $roles
     * @return Service[]
     */
    public function getServicesParRoles($roles): array
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        return $this->createQueryBuilder('s')
            ->where('s.supprimeLe IS NULL')
            ->andWhere('JSON_CONTAINS(s.roles, :roles) = 1')
            ->setParameter('roles', json_encode($roles))
            ->getQuery()
            ->getResult();
    }

    /**
     * listing des équipes "Pilotes" (équipes dont le flag estPilotageDme est à true)
     */
    public function getPilotageEquipes(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.estPilotageDme = true')
            ->andWhere('s.supprimeLe IS NULL')
            ->orderBy('s.label', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Listing des ESI pour les écrans de restitutions.
     * @return array
     */
    public function restitutionListingEsi() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT s.id as id, s.label as label, COUNT(c) as nbComposants
            FROM App\Entity\Composant c
            INNER JOIN c.exploitant s
            WHERE
                c.archiveLe IS NULL AND
                s.supprimeLe IS NULL
            GROUP BY id, label
            ORDER BY label ASC'
        );
        return $query->getResult();
    }

    /**
     * Listing des services de pilotage pour les écrans de restitutions.
     * @return array
     */
    public function restitutionListingEquipesPilotage() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT s.id as id, s.label as label, COUNT(c) as nbComposants
            FROM App\Entity\Service s
            LEFT JOIN s.composantsEquipe c
            WHERE
                c.archiveLe IS NULL AND
                s.supprimeLe IS NULL AND
                s.estPilotageDme = true
            GROUP BY id, label
            ORDER BY label ASC'
        );
        return $query->getResult();
    }

    /**
     * Listing des services de pilotage pour les écrans de restitutions.
     * @return array
     */
    public function restitutionListingBureauxRattachement() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT s.id as id, s.label as label, COUNT(c) as nbComposants
            FROM App\Entity\Composant c
            INNER JOIN c.bureauRattachement s
            WHERE
                c.archiveLe IS NULL AND
                s.supprimeLe IS NULL
            GROUP BY id, label
            ORDER BY label ASC'
        );
        return $query->getResult();
    }

    /**
     * Listing des services pour les écrans de restitutions.
     * @return array
     */
    public function restitutionListingServices() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT s.id as id, s.label as label, COUNT(c) as nbComposants
            FROM App\Entity\Composant\Annuaire a
            INNER JOIN a.composant c
            INNER JOIN a.service s
            WHERE
                c.archiveLe IS NULL AND
                s.supprimeLe IS NULL AND
                a.id IN (
                    SELECT MIN(a2.id)
                    FROM App\Entity\Composant\Annuaire a2
                    WHERE a2.supprimeLe IS NULL
                    GROUP BY a2.service, a2.composant, a2.mission
                )
            GROUP BY id, label
            ORDER BY label ASC'
        );
        return $query->getResult();
    }

    /**
     * Liste des services (ou 1 service) avec composants (ou 1 composants) et évènements associées.
     *
     * @param \DateTime    $periodeDebut
     * @param \DateTime    $periodeFin
     * @param Service|null $serviceExploitant
     * @param array|null   $composants
     *
     * @return array
     */
    public function listeServicesExploitantAvecComposantsEtEvenements(?Service $serviceExploitant, ?array $composants) : array
    {
        // Requête de base
        $query = $this->createQueryBuilder("s")
            ->select([
                's',
                'cex',
                'c_em',
                'cem_i',
                'cem_to'
            ])
            ->andWhere('s.supprimeLe IS NULL')
            ->andWhere('s.estServiceExploitant = true')
            ->leftJoin('s.composantsExploitant', 'cex')
            ->leftJoin('cex.evenementsMeteo', 'c_em')
            ->leftJoin('c_em.impact', 'cem_i')
            ->leftJoin('c_em.typeOperation', 'cem_to')
            ->andWhere('cex.meteoActive = true')
            ->orderBy('s.label, cex.label', 'ASC');

        // Si un service exploitant est saisie, on filtre avec le service passé en paramètre.
        if ($serviceExploitant !== null) {
            $query->andWhere('s.id = :exploitant OR s.structurePrincipale = :exploitant')
                ->setParameter('exploitant', $serviceExploitant);
        }

        // Si un/des composants sont passés en paramètres, on filtre avec le/les composants passés en paramètre.
        if ($composants !== null) {
            $query->andWhere('cex.id IN (:composants)')
                ->setParameter('composants', $composants);
        }

        // On effectue la requête et on renvoi le résultat
        return $query->getQuery()->getResult();
    }

    /*
     * Liste des services exploitants non supprimés.
     * @return array
     */
    public function listeServicesExploitantsMeteo() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT s
            FROM App\Entity\Service s
            LEFT JOIN s.composantsExploitant cex
            WHERE
                cex.meteoActive = true AND
                cex.archiveLe IS NULL AND
                s.supprimeLe IS NULL
            ORDER BY s.label ASC'
        );
        return $query->getResult();
    }

    /**
     * Récupère les équipes des composants fournis
     * @param array $ids
     * @return array
     */
    public function listeEquipesParComposants(array $ids): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('c')
            ->join('s.composantsEquipe', 'c')
            ->where('c.id IN (:composantsIds)')->setParameter('composantsIds', $ids)
            ->groupBy('c.id')
            ->addGroupBy('s.id')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Forme une requête permettant de lister les services associés à une balf donnée.
     * @param string $balf
     * @return array
     */
    public function rechercheParBalf(string $balf): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.email LIKE :balf')
            ->setParameter('balf', '%' . mb_strtolower($balf) . '%')
            ->orderBy('s.label')
            ->andWhere('s.supprimeLe IS NULL')
            ->getQuery()
            ->getResult();
    }
}
