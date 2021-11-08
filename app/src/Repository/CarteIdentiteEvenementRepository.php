<?php

namespace App\Repository;

use App\Entity\CarteIdentite;
use App\Entity\CarteIdentiteEvenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CarteIdentiteEvenement|null find($id, $lockMode = null, $lockVersion = null)
 * @method CarteIdentiteEvenement|null findOneBy(array $criteria, array $orderBy = null)
 * @method CarteIdentiteEvenement[]    findAll()
 * @method CarteIdentiteEvenement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CarteIdentiteEvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarteIdentiteEvenement::class);
    }

    /**
     * Fonction permettant de rendre l'historique du composant de la carte d'identité passé en paramètre.
     * @param CarteIdentite $carteIdentite
     *
     * @return CarteIdentiteEvenement[]
     */
    public function historiqueCompletDe(CarteIdentite $carteIdentite): array
    {
        // On initialise notre requête de récupération d'historique
        $query = $this->createQueryBuilder('h')
            ->select(['h', 's'])
            ->leftJoin('h.service', 's')
            ->orderBy('h.id', 'DESC');

        // Si la carte d'identité provient d'un composant de gesip, on passe par la relation "composant"
        if ($carteIdentite->getComposant()) {
            $query->andWhere('h.composant = :composant')
                ->setParameter('composant', $carteIdentite->getComposant());
        // Si la carte d'identité provient d'un composant qui n'est pas dans gesip, on passe par la relation "composant_carte_identité"
        } elseif ($carteIdentite->getComposantCarteIdentite()) {
            $query->andWhere('h.composantCarteIdentite = :composant')
                ->setParameter('composant', $carteIdentite->getComposantCarteIdentite());
        } else {
            return [];
        }

        // On renvoi le résultat
        return $query->getQuery()->getResult();
    }
}
