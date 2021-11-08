<?php

namespace App\Repository\References;

use App\Entity\References\Mission;

class MissionRepository extends ReferenceRepository
{
    protected $entityClass = Mission::class;

    /**
     * liste de toutes les missions
     * @return Mission[]
     */
    public function listeToutesMissions(): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT m
            FROM App\Entity\References\Mission m
            WHERE m.supprimeLe is null
            ORDER by m.label ASC'
        );

        return $query->getResult();
    }

    /**
     * Listing des missions pour les écrans de restitutions.
     * @return array
     */
    public function restitutionListing() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT m.id as id, m.label as label, COUNT(DISTINCT(c)) as nbComposants
            FROM App\Entity\Composant\Annuaire a
            INNER JOIN a.mission m
            INNER JOIN a.composant c
            WHERE
                a.supprimeLe IS NULL AND
                c.archiveLe IS NULL AND
                m.supprimeLe IS NULL
            GROUP BY id, label
            ORDER BY label ASC'
        );
        return $query->getResult();
    }

    /**
     * Listing des composants pour une mission donnée
     * @param Mission $mission
     * @return array
     */
    public function composants(Mission $mission) : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT c
            FROM App\Entity\Composant\Annuaire a
            LEFT JOIN a.mission m
            LEFT JOIN a.composant c
            WHERE
                c.archiveLe IS NULL AND
                m.supprimeLe IS NULL AND
                m.id = :mission
            ORDER BY label ASC'
        );
        $query->setParameter('mission', $mission);
        return $query->getResult();
    }
}
