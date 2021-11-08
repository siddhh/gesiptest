<?php

namespace App\Repository;

use App\Entity\ModeleCarteIdentite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ModeleCarteIdentite|null find($id, $lockMode = null, $lockVersion = null)
 * @method ModeleCarteIdentite|null findOneBy(array $criteria, array $orderBy = null)
 * @method ModeleCarteIdentite[]    findAll()
 * @method ModeleCarteIdentite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ModeleCarteIdentiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeleCarteIdentite::class);
    }

    /**
     * Retourne le modèle de carte d'identité actif
     * @return ModeleCarteIdentite|null
     */
    public function getModeleCarteIdentiteActif(): ?ModeleCarteIdentite
    {
        return $this->createQueryBuilder('mci')
            ->where('mci.actif = true')
            ->orderBy('mci.majLe', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Active le modèle de carte d'identité fourni et désactive les autres
     * @param ModeleCarteIdentite $modeleCarteIdentite
     * @return void
     */
    public function activeModele(ModeleCarteIdentite $modeleCarteIdentite): void
    {
        $this->getEntityManager()
            ->createQuery('
                UPDATE App\Entity\ModeleCarteIdentite mci
                    SET mci.actif = CASE WHEN mci.id = :modeleActifId THEN true ELSE false END
            ')
            ->setParameter('modeleActifId', $modeleCarteIdentite->getId())
            ->execute();
    }
}
