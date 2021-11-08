<?php

namespace App\Repository\References;

use App\Entity\References\Domaine;

class DomaineRepository extends ReferenceRepository
{
    protected $entityClass = Domaine::class;

    /**
     * Listing des Domaines pour les écrans de restitutions.
     * @return array
     */
    public function restitutionListing() : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT d.id as id, d.label as label, COUNT(c) as nbComposants
            FROM App\Entity\Composant c
            INNER JOIN c.domaine d
            WHERE
                c.archiveLe IS NULL AND
                d.supprimeLe IS NULL
            GROUP BY id, label
            ORDER BY label ASC'
        );
        return $query->getResult();
    }
}
