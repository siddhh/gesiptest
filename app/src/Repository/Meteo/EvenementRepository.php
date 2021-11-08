<?php

namespace App\Repository\Meteo;

use App\Entity\Demande\ImpactReel;
use App\Entity\Service;
use App\Entity\Meteo\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Evenement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evenement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evenement[]    findAll()
 * @method Evenement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Retourne les demandes d'intervention par composant, status et date de début / fin
     * @param int[] $idsComposants
     * @param \DateTimeInterface $debut
     * @param \DateTimeInterface $fin
     * @param Service $saisiPar
     * @return Evenement[]
     */
    public function listeEvenements(array $idsComposants, \DateTimeInterface $debut, \DateTimeInterface $fin, ?Service $saisiPar = null): array
    {
        // Construction de la requête et renvoi du résultat
        $qb = $this->createQueryBuilder('e')
            ->addSelect(['m', 'i'])
            ->join('e.typeOperation', 'm')
            ->join('e.impact', 'i')
            ->where('e.composant IN (:idComposants)')
            ->setParameter('idComposants', $idsComposants)
            ->andWhere('e.debut <= :fin AND e.fin >= :debut')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('e.debut', 'ASC');

        // Filtre supplémentaire si le service est fourni
        if (!empty($saisiPar)) {
            $qb->andWhere('e.saisiePar = :serviceId')
                ->setParameter('serviceId', $saisiPar);
        }

        // Renvoi du résultat
        return $qb->getQuery()->getResult();
    }

    /**
     * Fonction permettant de récupérer les composants impactés sur une période donnée.
     *
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     *
     * @return array
     */
    public function listeComposantsPeriode(\DateTime $periodeDebut, \DateTime $periodeFin) : array
    {
        return $this->createQueryBuilder('e')
            ->select('c.id')
            ->where('e.debut >= :debut AND e.debut <= :fin')
            ->orWhere('e.fin >= :debut AND e.fin <= :fin')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin)
            ->groupBy('c.id')
            ->join('e.composant', 'c')
            ->getQuery()
            ->getResult();
    }

    /**
     * Fonction permettant de récupérer les évènements saisies pour une période donnée.
     *
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     *
     * @return array
     */
    public function listeEvenementsPeriode(\DateTime $periodeDebut, \DateTime $periodeFin) : array
    {
        return $this->createQueryBuilder('e')
            ->where('e.debut >= :debut AND e.debut <= :fin')
            ->orWhere('e.fin >= :debut AND e.fin <= :fin')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin)
            ->getQuery()->getResult();
    }

    /**
     * Forme une requête permettant de lister les évènements par une liste de composants.
     *
     * @param array     $composants
     * @param \DateTime $debutMois
     * @param \DateTime $finMois
     *
     * @return Evenement[]
     */
    public function listeEvenementsFiltre(array $composants, \DateTime $debutMois, \DateTime $finMois): array
    {
        $qb = $this->createQueryBuilder('e')
            ->addSelect(['c', 'i'])
            ->join('e.composant', 'c')
            ->join('e.impact', 'i')
            ->where('c.meteoActive = true')
            ->andWhere('(e.fin >= :debutMois AND e.debut <= :finMois)')
            ->setParameter('debutMois', $debutMois)->setParameter('finMois', $finMois)
            ->andWhere('i.label NOT IN (:excluImpactLabels)')
            ->setParameter('excluImpactLabels', [
                'Aucun impact',
                'Transparent pour les utilisateurs',
                'Impact ponctuel MMA'
            ]);

        if (count($composants)) {
            $qb->andWhere('e.composant IN (:composants)')
                ->setParameter('composants', $composants);
        }

        $qb-> orderBy('c.label', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère la liste des évènements ayant fait l'objet d'un transfert d'impacts (prévisionnel ou réels) dont les ids et la période sont passés en paramètres.
     *
     * @param string    $impactType
     * @param array     $idsImpacts
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     *
     * @return ArrayCollection
     */
    public function impactsDejaTransfereesMeteo(string $impactType, array $idsImpacts, \DateTime $periodeDebut, \DateTime $periodeFin): ArrayCollection
    {
        // On passe les dates en UTC
        $periodeDebut = (clone $periodeDebut)->setTimezone(new \DateTimeZone('utc'));
        $periodeFin = (clone $periodeFin)->setTimezone(new \DateTimeZone('utc'));

        // On définie la colonne à aller chercher en fonction du type d'impact demandé
        $impactType = $impactType === ImpactReel::class ? 'impactsReel' : 'impactsPrevisionnel';

        // On prépare notre requête
        $query = $this->createQueryBuilder('e')
            ->where('e.debut <= :periodeFin AND e.fin >= :periodeDebut')
                ->setParameter('periodeDebut', $periodeDebut)
                ->setParameter('periodeFin', $periodeFin)
            ->andWhere('e.' . $impactType . ' IN (:idsImpacts)')
                ->setParameter('idsImpacts', $idsImpacts);

        // On lance la requête et on renvoi les résultats
        return new ArrayCollection($query->getQuery()->getResult());
    }
}
