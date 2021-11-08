<?php

namespace App\Repository;

use App\Entity\CarteIdentite;
use App\Entity\Composant;
use App\Entity\ComposantCarteIdentite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CarteIdentite|null find($id, $lockMode = null, $lockVersion = null)
 * @method CarteIdentite|null findOneBy(array $criteria, array $orderBy = null)
 * @method CarteIdentite[]    findAll()
 * @method CarteIdentite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CarteIdentiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarteIdentite::class);
    }

    /**
     * Récupère l'historique d'une carte d'identite
     * @param int $carteIdentiteId
     * @return CarteIdentite
     */
    public function historique(int $carteIdentiteId): CarteIdentite
    {
        return $this->createQueryBuilder('ci')
            ->addSelect('cg', 'cge', 'cges', 'cci', 'ccie', 'ccies')
            ->where('ci.id = :carteIdentiteid')
            ->setParameter('carteIdentiteid', $carteIdentiteId)
            ->leftJoin('ci.composant', 'cg')
            ->leftJoin('cg.carteIdentiteEvenements', 'cge')
            ->leftJoin('cge.service', 'cges')
            ->leftJoin('ci.composantCarteIdentite', 'cci')
            ->leftJoin('cci.carteIdentiteEvenements', 'ccie')
            ->leftJoin('ccie.service', 'ccies')
            ->orderBy('cge.ajouteLe', 'DESC')
            ->getQuery()
            ->getSingleResult();
    }

    /*
     * Retourne une liste de cartes d'identité
     * @param array $filtres Permet de filtrer les cartes d'identité retournées dans l'ordre croissant (la version courante étant la dernière)
     * @return CarteIdentite[]
     */
    public function getCarteIdentites(array $filtres = []): array
    {
        $baseQueryBuilder = $this->createQueryBuilder('ci')
            ->addSelect('cg', 'cci', 's')
            ->leftJoin('ci.composant', 'cg')
            ->leftJoin('ci.composantCarteIdentite', 'cci')
            ->join('ci.service', 's')
            ->orderBy('ci.ajouteLe', 'ASC');
        if ($filtres['composant'] instanceof Composant) {
            $baseQueryBuilder
                ->where('ci.composant IN (:composants)')
                ->setParameter('composants', $filtres['composant']);
        } elseif ($filtres['composant'] instanceof ComposantCarteIdentite) {
            $baseQueryBuilder
                ->where('ci.composantCarteIdentite IN (:composants)')
                ->setParameter('composants', $filtres['composant']);
        }
        return $baseQueryBuilder->getQuery()->getResult();
    }

    /**
     * Retourne la version courante de la carte d'identité du composant fourni en paramètre
     * @param string $className
     * @param int $composantId
     * @return CarteIdentite|null
     */
    public function getCarteIdentiteParComposant(string $className, int $composantId): ?CarteIdentite
    {
        if (Composant::class === $className) {
            return $this->createQueryBuilder('ci')
                ->addSelect('c', 's')
                ->join('ci.composant', 'c')
                ->join('ci.service', 's')
                ->where('ci.composant = :composant')
                ->setParameter('composant', $composantId)
                ->setMaxResults(1)
                ->orderBy('ci.ajouteLe', 'DESC')
                ->getQuery()
                ->getOneOrNullResult();
        } else {
            return $this->createQueryBuilder('ci')
                ->addSelect('c', 's')
                ->join('ci.composantCarteIdentite', 'c')
                ->join('ci.service', 's')
                ->where('ci.composantCarteIdentite = :composant')
                ->setParameter('composant', $composantId)
                ->setMaxResults(1)
                ->orderBy('ci.ajouteLe', 'DESC')
                ->getQuery()
                ->getOneOrNullResult();
        }
    }

    /**
     * Permet de récupérer les cartes d'identité qui ne sont pas encore totalement transmise par un admin.
     *
     * @return array
     */
    public function getMajOuCreationParServices(): array
    {
        return $this->createQueryBuilder('ci')
            ->addSelect('ccc', 'c', 's')
            ->leftJoin('ci.composantCarteIdentite', 'ccc')
            ->leftJoin('ci.composant', 'c')
            ->join('ci.service', 's')
            ->where('ci.transmissionServiceManager = false or ci.transmissionSwitch = false or ci.transmissionSinaps = false')
            ->andWhere('ci.visible = true')
            ->andWhere('c.label IS NOT NULL or ccc.label IS NOT NULL')
            ->orderBy('ci.ajouteLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
