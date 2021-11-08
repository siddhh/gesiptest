<?php

namespace App\Repository;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatTerminee;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatSaisirRealise;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DemandeIntervention|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandeIntervention|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandeIntervention[]    findAll()
 * @method DemandeIntervention[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeInterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeIntervention::class);
    }

    /**
     * Recherche les demandes d'intervention
     * @return DemandeIntervention[]
     */
    public function rechercheDemandesIntervention(array $filters = null): array
    {
        // Construction de la requête principale permettant de charger les données nécessaires à l'affichage (tente d'éviter le "lazy loading")
        $filterHistoryStatus = [];

        $mainQueryBuilder = $this->createQueryBuilder('d1')
            ->addSelect(['sd1', 'se1', 'c1', 'm1', 'p1', 'ps1', 'ee1'])
            ->leftJoin('d1.demandePar', 'sd1')
            ->leftJoin('d1.services', 'se1')
            ->leftJoin('d1.composant', 'c1')
            ->leftJoin('c1.pilote', 'p1')
            ->leftJoin('c1.piloteSuppleant', 'ps1')
            ->leftJoin('d1.motifIntervention', 'm1')
            ->leftJoin('d1.exploitantExterieurs', 'ee1')
            ->andWhere('d1.supprimeLe IS NULL')
            ->orderBy('d1.ajouteLe', 'DESC');

        // Construction d'une sous-requête permettant de sélectionner les interventions sur la base des filtres fournis en paramètres
        $conditionsQueryBuilder = $this->createQueryBuilder('d2')->select('d2.id');
        if (!empty($filters['numero'])) {
            $conditionsQueryBuilder->andWhere('LOWER(d1.numero) LIKE :numero');
            $mainQueryBuilder->setParameter('numero', '%' . mb_strtolower($filters['numero']) . '%');
        }
        if (!empty($filters['demandePar'])) {
            $conditionsQueryBuilder->andWhere('d2.demandePar = :serviceDemandeur');
            $mainQueryBuilder->setParameter('serviceDemandeur', $filters['demandePar']);
        }
        if (!empty($filters['exploitant'])) {
            $conditionsQueryBuilder->leftJoin('d2.services', 'se2');
            $conditionsQueryBuilder->leftJoin('d2.exploitantExterieurs', 'ee2');
            $conditionsQueryBuilder->andWhere('se2.service = :serviceExploitant OR ee2.id = :serviceExploitant');
            $mainQueryBuilder->setParameter('serviceExploitant', $filters['exploitant']);
        }
        if (!empty($filters['composantConcerne']) && !is_array($filters['composantConcerne'])) {
            $conditionsQueryBuilder->andWhere('d2.composant = :composantConcerne');
            $mainQueryBuilder->setParameter('composantConcerne', $filters['composantConcerne']);
        }
        if (!empty($filters['composantConcerne']) && is_array($filters['composantConcerne'])) {
            $conditionsQueryBuilder->andWhere('d2.composant IN (:composantConcerne)');
            $mainQueryBuilder->setParameter('composantConcerne', $filters['composantConcerne']);
        }
        if (!empty($filters['composantImpacte'])) {
            $subConditionsImpactComposantsQueryBuilder = $this->createQueryBuilder('d3')
                ->select('d3.id')
                ->join('d3.impacts', 'i3')
                ->join('i3.composants', 'ic3')
                ->where('ic3.id = :composantImpacte');
            $subConditionsImpactReelsComposantsQueryBuilder = $this->createQueryBuilder('d4')
                ->select('d4.id')
                ->join('d4.saisieRealises', 'sr4')
                ->join('sr4.impactReels', 'ir4')
                ->join('ir4.composants', 'irc4')
                ->where('irc4.id = :composantImpacte');
            $conditionsQueryBuilder->andWhere('(d2.id IN (' . $subConditionsImpactComposantsQueryBuilder->getDQL() . ')
                OR d2.id IN (' . $subConditionsImpactReelsComposantsQueryBuilder->getDQL() . '))');
            $mainQueryBuilder->setParameter('composantImpacte', $filters['composantImpacte']);
        }
        if (!empty($filters['pilote'])) {
            $conditionsQueryBuilder->leftJoin('d2.composant', 'c2');
            $conditionsQueryBuilder->andWhere('c2.pilote = :pilote OR c2.piloteSuppleant = :pilote');
            $mainQueryBuilder->setParameter('pilote', $filters['pilote']);
        }
        if (!empty($filters['motifIntervention'])) {
            $conditionsQueryBuilder->andWhere('d2.motifIntervention = :motifIntervention');
            $mainQueryBuilder->setParameter('motifIntervention', $filters['motifIntervention']);
        }
        if (!empty($filters['periodeDateDebut'])) {
            $dateString = $filters['periodeDateDebut']->format('Y-m-d');
            $conditionsQueryBuilder->andWhere('d2.dateFinMax >= :periodeDateDebut');
            $mainQueryBuilder->setParameter('periodeDateDebut', $dateString . ' 00:00:00');
        }
        if (!empty($filters['periodeDateFin'])) {
            $dateString = $filters['periodeDateFin']->format('Y-m-d');
            $conditionsQueryBuilder->andWhere('d2.dateDebut <= :periodeDateFin');
            $mainQueryBuilder->setParameter('periodeDateFin', $dateString . ' 23:59:59');
        }
        if (!empty($filters['demandeLe'])) {
            $dateString = $filters['demandeLe']->format('Y-m-d');
            $conditionsQueryBuilder->andWhere('d2.demandeLe BETWEEN :demandeLeStart AND :demandeLeEnd');
            $mainQueryBuilder->setParameter('demandeLeStart', $dateString . ' 00:00:00');
            $mainQueryBuilder->setParameter('demandeLeEnd', $dateString . ' 23:59:59');
        }
        $statusInactivesInterventions = [
            EtatAnnulee::class,
            EtatRefusee::class,
            EtatInterventionReussie::class,
            EtatInterventionEchouee::class,
            EtatTerminee::class
        ];
        if (!empty($filters['status'])) {
            $filterHistoryStatus = explode('<', $filters['status']);
            $filterHistoryStatusBeforePurge = explode(',', array_shift($filterHistoryStatus));
            if (array_key_exists('interventionsActives', $filters) && ($filters['interventionsActives'] == true)) {
                $filterHistoryStatusAfterPurge = array_diff($filterHistoryStatusBeforePurge, $statusInactivesInterventions);
            } else {
                $filterHistoryStatusAfterPurge = $filterHistoryStatusBeforePurge;
            }
            $conditionsQueryBuilder->andWhere('d2.status IN (:status)');
            $mainQueryBuilder->setParameter('status', $filterHistoryStatusAfterPurge);
        } else {
            if (array_key_exists('interventionsActives', $filters) && ($filters['interventionsActives'] == true)) {
                $filterHistoryStatusAfterMerge = array_merge($statusInactivesInterventions, [ EtatBrouillon::class ]);
            } else {
                $filterHistoryStatusAfterMerge = [ EtatBrouillon::class ];
            }
            $conditionsQueryBuilder->andWhere('d2.status NOT IN (:status)');
            $mainQueryBuilder->setParameter('status', $filterHistoryStatusAfterMerge);
        }
        if (!empty($filters['retard'])) {
            $tableauDIEnRetard = $this->getDemandeInterventionsPourCalculRetardDecisionDme();
            $conditionsQueryBuilder->andWhere('d.id IN (:diEnRetard)');
            $mainQueryBuilder->setParameter('diEnRetard', $tableauDIEnRetard);
        }
        if (!empty($filters['noDraft'])) {
            $conditionsQueryBuilder->andWhere('d2.demandeLe IS NOT NULL');
        }

        //  On injecte alors la sous-requête dans la requête principale et on recupère le résultat
        $mainQueryBuilder->andWhere('d1.id IN (' . $conditionsQueryBuilder->getDQL() . ')');
        $result = $mainQueryBuilder->getQuery()->getResult();

        // Si on doit aussi filtrer sur l'état précédent
        if (!empty($filterHistoryStatus)) {
            $filterPreviousStatus = explode(',', array_shift($filterHistoryStatus));
            foreach ($result as $index => $demandeIntervention) {
                $historiqueStatus = $demandeIntervention->getHistoriqueStatus();
                if (empty($historiqueStatus[1]) || !in_array($historiqueStatus[1]->getStatus(), $filterPreviousStatus)) {
                    unset($result[$index]);
                }
            }
        }

        // On renvoie le résultat
        return $result;
    }

    /**
     * recherche de demande d intervention par id
     * @return array[]
     */
    public function multiSelectSearchByLabel($labelSearch): array
    {
        // On se prépare
        $resultats = [];
        $labelSearch = mb_strtolower($labelSearch) . '%';
        $entityManager = $this->getEntityManager();

        // On lance notre requête
        $query = $entityManager->createQuery(
            'SELECT PARTIAL d.{ id, numero, composant, demandePar }, PARTIAL c.{ id, label }, PARTIAL s.{ id, label }
            FROM App\Entity\DemandeIntervention d
            JOIN d.composant c
            JOIN d.demandePar s
            WHERE
                UPPER(d.numero) LIKE UPPER(:labelSearch) AND
                d.supprimeLe IS null AND
                d.status <> :status
            ORDER by d.numero ASC'
        )
        ->setParameter('labelSearch', $labelSearch)
        ->setParameter('status', EtatBrouillon::class)
        ->getResult();

        // On ajoute une entête pour l'affichage des résultats du champ
        $resultats[] = [
            'numero' => 'Numéro',
            'label' => 'Label',
            'demandeur' => 'Demandeur',
        ];

        // On met en forme
        foreach ($query as $demande) {
            $resultats[] = [
                'id' => $demande->getId(),
                'numero' => $demande->getNumero(),
                'label' => $demande->getComposant()->getLabel(),
                'demandeur' => $demande->getDemandePar()->getLabel()
            ];
        }

        // On renvoi les résultats
        return $resultats;
    }

    /**
     * Recherche une intervention sur une période donnée (les demandes fermées: annulée ou refusée sont exclues)
     * @return DemandeIntervention[]
     */
    public function listeDemandeInterventionParPeriode(\DateTime $dtStart, \DateTime $dtEnd): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT d
                FROM App\Entity\DemandeIntervention d
                WHERE (NOT d.status IN (:etatExclus))
                    AND ((d.dateDebut >= :start AND d.dateDebut <= :end) OR (d.dateFinMax >= :start AND d.dateFinMax <= :end))
                ORDER BY d.dateDebut ASC
            '
        )
        ->setParameter('start', $dtStart)
        ->setParameter('end', $dtEnd)
        ->setParameter('etatExclus', [
            EtatAnnulee::class,
            EtatRefusee::class,
        ])
        ->getResult();
    }

    /**
     * Retourne une liste des interventions correspondant aux critères fournis en paramètres
     * @param array $status
     * @param Service|null $demandePar
     * @param Service|null $equipe
     * @param Service|null $exploitant
     * @return DemandeIntervention[]
     */
    public function listerDemandesInterventionParServiceEtats(array $status, ?Service $demandePar = null, ?Service $equipe = null, ?Service $exploitant = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect('c', 'm', 'a', 's')
            ->join('d.composant', 'c')
            ->join('d.motifIntervention', 'm')
            ->leftJoin('d.services', 'a')
            ->leftJoin('a.service', 's')
            ->leftJoin('d.exploitantExterieurs', 'ee')
            ->where('d.supprimeLe IS NULL')
            ->andWhere('d.status IN (:status)')
            ->orderBy('d.demandeLe', 'DESC')
            ->addOrderBy('d.composant', 'ASC')
            ->setParameter('status', $status);

        if ($demandePar instanceof Service) {
            if ($exploitant instanceof Service) {
                $qb->leftJoin('d.services', 'a2')
                    ->andwhere('(d.demandePar = :demandePar OR a2.service = :exploitant OR ee.id = :exploitant)')
                    ->setParameter('demandePar', $demandePar)
                    ->setParameter('exploitant', $exploitant);
            } else {
                $qb->andwhere('d.demandePar = :demandePar')
                    ->setParameter('demandePar', $demandePar);
            }
        }

        if ($equipe instanceof Service) {
            $qb->andWhere('c.equipe = :equipe')
                ->setParameter('equipe', $equipe);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne la liste des demandes d'intervention ordonnée par equipe
     */
    public function getDemandeInterventionsEnCoursOrdonneParEquipe(string $status = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.composant', 'c')
            ->leftJoin('c.equipe', 'e')
            ->orderBy('e.label', 'ASC')
            ->addOrderBy('d.dateDebut', 'ASC');
        if (!empty($status)) {
            $qb
                ->where('d.status = :status')
                ->setParameter(':status', $status);
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne la liste des demandes d'interventions dans le status donné.
     *
     * @param array|string|null $status
     * @param bool|null $avecEquipe
     * @param \Closure|null $closure
     * @return array
     */
    public function getDemandeInterventionsParEquipes($status = null, ?bool $avecEquipe = null, ?\Closure $closure = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('e', 'c', 'd', 'm', 's')
            ->join('d.composant', 'c')
            ->leftJoin('c.equipe', 'e')
            ->join('d.motifIntervention', 'm')
            ->leftJoin('d.services', 's')
            ->orderBy('e.label', 'ASC')
            ->addOrderBy('DATE_FORMAT(d.dateDebut, \'DD/MM/YYYY\')', 'ASC')
            ->addOrderBy('c.label', 'ASC')
            ->addOrderBy('d.demandeLe', 'ASC');

        if (!is_null($avecEquipe)) {
            if ($avecEquipe) {
                $qb->where('c.equipe IS NOT NULL');
            } else {
                $qb->where('c.equipe IS NULL');
            }
        }
        if ($status !== null) {
            if (is_array($status)) {
                $qb
                    ->where('d.status IN (:status)')
                    ->setParameter(':status', $status);
            } else {
                $qb
                    ->where('d.status = :status')
                    ->setParameter(':status', $status);
            }
        }

        if ($closure !== null) {
            $closure($qb);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne la liste des demandes d'interventions status renvoyé.
     */
    public function getDemandeInterventionsRenvoyeesAvecAncienStatus(string $status, ?Service $equipe = null, ?Service $demandePar = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('e', 'c', 'd', 'm', 's', 'h')
            ->join('d.historiqueStatus', 'h')
            ->join('d.composant', 'c')
            ->leftJoin('c.equipe', 'e')
            ->join('d.motifIntervention', 'm')
            ->leftJoin('d.services', 's')
            ->orderBy('e.label', 'ASC')
            ->addOrderBy('h.id', 'DESC')
            ->where('d.status = :status')
            ->setParameter('status', $status);

        if ($equipe instanceof Service) {
            $qb->andwhere('c.equipe = :equipe')
               ->setParameter('equipe', $equipe);
        }
        if ($demandePar instanceof Service) {
            $qb->andwhere('d.demandePar = :demandePar')
               ->setParameter('demandePar', $demandePar);
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne la liste des demandes d'interventions en retard
     */
    public function getDemandeInterventionsPourCalculRetardDecisionDme(?DateTime $dateDesRetards = null): array
    {
        // On initialise notre tableau de demandes en retards
        $demandesEnRetard = [];

        // On récupère la date de retard, si null alors on prend la date du jour
        if ($dateDesRetards === null) {
            $dateDesRetards = new \DateTime();
        }
        $dateDesRetards->setTime(0, 0, 0);

        // On sélectionne toutes les demandes potentiellement en retard, qui ne sont actuellement en status :
        // Analyse en cours / Consultation en cours CDB ou non / Instruite
        $queryDemandes = $this->createQueryBuilder('d')
            ->select(['d', 'c', 'h'])
            ->join('d.composant', 'c')
            ->join('d.historiqueStatus', 'h')
            ->where('d.status IN (:status)')
                ->setParameter('status', [
                    EtatAnalyseEnCours::class,
                    EtatConsultationEnCoursCdb::class,
                    EtatConsultationEnCours::class,
                    EtatInstruite::class
                ])
            ->getQuery()
            ->getResult();

        // On parcourt les demandes de la base de données
        /** @var DemandeIntervention $demande */
        foreach ($queryDemandes as $demande) {
            if ($demande->decisionDmeEnRetard($dateDesRetards)) {
                $demandesEnRetard[] = $demande;
            }
        }

        return $demandesEnRetard;
    }

    /**
     * Fonction permettant de restituer les demandes d'intervention par composants.
     * @param array $composants
     * @param string $order
     * @return array
     */
    public function restitutionDemandes(array $composants, string $order = 'DESC') : array
    {
        // Construction de la requête principale permettant de charger les données nécessaires à l'affichage (tente d'éviter le "lazy loading")
        $qb = $this->createQueryBuilder('d')
            ->join('d.composant', 'c')
            ->orderBy('d.dateDebut', $order);

        // Filtrage par composants
        $qb->andWhere('d.composant IN (:composants)')
            ->setParameter('composants', $composants);

        // Uniquement les demandes "Accordée", "Intervention en cours" et "Réalisé à saisir"
        $qb->andWhere('d.status IN (:status)')
            ->setParameter('status', [
                EtatAccordee::class,
                EtatInterventionEnCours::class,
                EtatSaisirRealise::class
            ]);

        // On exécute et rend les résultats
        return $qb->getQuery()->getResult();
    }

    /*
     * Retourne la liste des demandes d'interventions pour le tableau de bord
     * @param array $filters
     * @return array
     */
    public function rechercheDemandeInterventionTableauBord(array $filters = []): array
    {
        // Etats qui ne doivent pas apparaitre dans le tableau de bord
        $hiddenStatus = [
            EtatRefusee::class,
            EtatAnnulee::class,
            EtatTerminee::class,
            EtatBrouillon::class,
            EtatInterventionEchouee::class,
            EtatInterventionReussie::class,
        ];

        // Requète de base
        $qb = $this->createQueryBuilder('d')
            ->addSelect(['sd', 'se', 'ee', 'c','sr', 'p', 'h'])
            ->join('d.demandePar', 'sd')
            ->leftJoin('d.services', 'se')
            ->leftJoin('d.exploitantExterieurs', 'ee')
            ->join('d.composant', 'c')
            ->leftJoin('d.historiqueStatus', 'h')
            ->leftJoin('d.saisieRealises', 'sr')
            ->leftJoin('c.pilote', 'p')
            ->andWhere('d.status NOT IN (:hiddenStatus)')
            ->setParameter('hiddenStatus', $hiddenStatus)
            ->orderBy('d.dateDebut', 'ASC')
            ->addOrderBy('d.numero', 'ASC');

        // Ajout les filtres
        if (!empty($filters['status'])) {
            $qb->andWhere('d.status IN (:status)');
            $qb->setParameter('status', $filters['status']);
        }
        if (!empty($filters['not-status'])) {
            $qb->andWhere('d.status NOT IN (:notstatus)');
            $qb->setParameter('notstatus', $filters['not-status']);
        }
        if (!empty($filters['etatInterventionEnCoursOrEquipeOrNull'])) {
            $qb->andWhere('(d.status IN (:statusEquipe) OR c.equipe = :equipeId OR c.equipe IS NULL)');
            $qb->setParameter('equipeId', $filters['etatInterventionEnCoursOrEquipeOrNull']);
            $qb->setParameter('statusEquipe', EtatInterventionEnCours::class);
        }
        if (!empty($filters['equipeOrNull'])) {
            $qb->andWhere('(c.equipe = :equipeId OR c.equipe IS NULL)');
            $qb->setParameter('equipeId', $filters['equipeOrNull']);
        }
        if (!empty($filters['equipe'])) {
            if ($filters['equipe'] === "sans-equipe-associee") {
                $qb->andWhere('(c.equipe is null)');
            } else {
                $qb->andWhere('(c.equipe = :equipeId)');
                $qb->setParameter('equipeId', $filters['equipe']);
            }
        }
        if (!empty($filters['demandeParOrexploitant'])) {
            $qb->andWhere('(d.demandePar = :serviceId OR se.service = :serviceId OR ee.id = :serviceId)');
            $qb->setParameter('serviceId', $filters['demandeParOrexploitant']);
        }
        if (!empty($filters['exploitant'])) {
            $qb->andWhere('(se.service = :serviceId OR ee.id = :serviceId)');
            $qb->setParameter('serviceId', $filters['exploitant']);
        }
        if (!empty($filters['demandePar'])) {
            $qb->andWhere('d.demandePar = :demandeParId');
            $qb->setParameter('demandeParId', $filters['demandePar']);
        }
        if (!empty($filters['natureIntervention'])) {
            $qb->andWhere('d.natureIntervention = :natureLabel');
            $qb->setParameter('natureLabel', $filters['natureIntervention']);
        }
        if (!empty($filters['retard'])) {
            $tableauDIEnRetard = $this->getDemandeInterventionsPourCalculRetardDecisionDme();
            $qb->andWhere('d.id IN (:diEnRetard)');
            $qb->setParameter('diEnRetard', $tableauDIEnRetard);
        }

        // Récupère le résultat de la requète
        $results = $qb->getQuery()->getResult();

        // Effectue une deuxieme passe de filtrage dans l'historique de la demande
        if (!empty($filters['retourConsultationNegatif'])) {
            foreach ($results as $index => $demande) {
                $historiqueStatus = $demande->getHistoriqueStatus();
                $keepRow = false;
                foreach ($historiqueStatus as $hsRow) {
                    $data = $hsRow->getDonnees();
                    if (!empty($data['avis'])) {
                        foreach ($data['avis'] as $avis) {
                            if ($avis['avis'] == "ko") {
                                $keepRow = true;
                                break;
                            }
                        }
                    }
                    if ($keepRow) {
                        break;
                    }
                }
                if (!$keepRow) {
                    unset($results[$index]);
                }
            }
        }

        // retourne le résultat obtenu
        return $results;
    }

    /*
     * Retourne la liste des demandes d'interventions dans un statut donnée et trop vieille
     * @param array $status
     * @param DateTime $dateFinMax
     * @return array
     */
    public function listeVieilleDemandeIntervention(array $status, \DateTime $dateFinMax): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect(['c', 'ca', 'i', 'h', 'sr', 'ir'])
            ->join('d.composant', 'c')
            ->leftJoin('c.annuaire', 'ca')
            ->leftJoin('d.impacts', 'i')
            ->leftJoin('d.historiqueStatus', 'h')
            ->leftJoin('d.saisieRealises', 'sr')
            ->leftJoin('sr.impactReels', 'ir')
            ->orderBy('d.id', 'ASC')
            ->where('d.status IN (:status)')
            ->setParameter('status', $status)
            ->andWhere('d.dateFinMax <= :startMax')
            ->setParameter('startMax', $dateFinMax)
        ;
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne la liste des demandes nécessitant potentiellement des changements d'état automatique
     */
    public function listeDemandesChangementAuto(): array
    {
        $dtNow = new \DateTime();
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :statusAccordee AND d.dateDebut <= :dateMaintenant')
            ->orWhere('d.status = :statusEnCours AND d.dateFinMax < :dateMaintenant')
            ->setParameter('statusAccordee', EtatAccordee::class)
            ->setParameter('statusEnCours', EtatInterventionEnCours::class)
            ->setParameter('dateMaintenant', $dtNow)
            ->orderBy('d.status', 'ASC')
            ->addOrderBy('d.dateDebut', 'ASC');
        ;
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les demandes d'intervention par composant, status et date de début / fin
     * @param Composant $composant
     * @param \DateTimeInterface $debut
     * @param \DateTimeInterface $fin
     * @return DemandeIntervention[]
     */
    public function listeDemandesInterventionParComposantSemaineMeteo(Composant $composant, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        // Seuls les etats suivants nous interesse pour la météo
        $listeStatus = [
            EtatSaisirRealise::class,
            EtatInterventionEnCours::class,
            EtatTerminee::class,
            EtatInterventionReussie::class,
            EtatInterventionEchouee::class,
        ];

        // Construction de la requete et renvoi du résultat
        return $this->createQueryBuilder('d')
            ->addSelect(['sd', 'se', 'm'])
            ->join('d.demandePar', 'sd')
            ->leftJoin('d.services', 'se')
            ->join('d.composant', 'c')
            ->join('d.motifIntervention', 'm')
            ->leftJoin('d.saisieRealises', 'sr')
            ->leftJoin('sr.impactReels', 'ir')
            ->leftJoin('ir.composants', 'irc')
            ->leftJoin('d.impacts', 'i')
            ->leftJoin('i.composants', 'ic')
            ->where('d.status IN (:status)')
            ->setParameter('status', $listeStatus)
            ->andWhere('(d.dateFinMax >= :debut AND d.dateDebut <= :fin)')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->andWhere('d.composant = :composantId OR ic.id = :composantId OR irc.id = :composantId')
            ->setParameter('composantId', $composant)
            ->orderBy('d.ajouteLe', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les demandes d'intervention pour les statistiques (état global)
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @param string $parBureauRattachement
     * @return DemandeIntervention[]
     */
    public function listeInterventionsEtatGlobal(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin, string $parBureauRattachement): array
    {
        $listeStatus = [
            EtatInterventionEnCours::class,
            EtatTerminee::class,
            EtatInterventionReussie::class,
            EtatInterventionEchouee::class,
        ];
        $qb = $this->createQueryBuilder('d')
            ->where('d.status IN (:status)')
            ->setParameter('status', $listeStatus)
            ->andWhere('d.dateDebut < :fin AND d.dateFinMax > :debut')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin);

        if ($parBureauRattachement  == "oui") {
            $qb->addSelect('c')
                ->join('d.composant', 'c')
                ->andWhere('c.bureauRattachement IS NOT NULL')
                ->orderBy('c.bureauRattachement');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les demandes d'intervention pour lesquelles l'avis du service est demandé
     * @param integer $annuaireId[]
     * @return DemandeIntervention[]
     */
    public function listeDemandesInterventionsPourAvis(array $annuaireId): array
    {
        $listeStatus = [
            EtatConsultationEnCours::class,
            EtatConsultationEnCoursCdb::class
        ];

        $recherche = json_encode(['annuaires' => [$annuaireId[0]]]);
        $selectAnnuaire = "JSON_CONTAINS(hs.donnees, '" . $recherche . "') = 1";
        for ($i = 1; $i < count($annuaireId); $i++) {
            $recherche = json_encode(['annuaires' => [$annuaireId[$i]]]);
            $selectAnnuaire .= " OR JSON_CONTAINS(hs.donnees, '" . $recherche . "') = 1";
        }

        $qb = $this->createQueryBuilder('d')
            ->where('d.status IN (:status)')
            ->setParameter('status', $listeStatus)
            ->join('d.historiqueStatus', 'hs')
            ->andWhere("hs.status = '" . EtatConsultationEnCours::class . "'")
            ->andWhere($selectAnnuaire)
            ->andWhere("JSON_GET(hs.donnees, 'dateLimite', 'DD/MM/YYYY') >= :dateDuJour")
            ->setParameter('dateDuJour', (new \DateTime())->format('Y-m-d'))
            ->addSelect("JSON_GET(hs.donnees, 'avis')")
            ->orderBy('d.dateDebut')
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les demandes d'intervention étant passées dans certains états au cours de la période demandée (pour les
     * statistiques)
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @param array $listeStatuts
     * @return DemandeIntervention[]
     */
    public function listeDemandesInterventionsParHistorique(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin, array $listeStatuts): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect('hss', 'c', 'p')
            ->join('d.composant', 'c')
            ->leftJoin('c.pilote', 'p')
            ->join('d.historiqueStatus', 'hss')
            ->join('d.historiqueStatus', 'hs')
            ->where('hs.status IN (:status)')
            ->setParameter('status', $listeStatuts)
            ->andWhere('hs.ajouteLe BETWEEN :debut AND :fin')
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin);
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les demandes d'intervention pour la vue inter-applicative
     * @param int $composantId[]
     * @return DemandeIntervention[]
     */
    public function listePourVueInterApplicative(array $composantId): array
    {
        $qb = $this->createQueryBuilder('di')
            ->addSelect('c', 'd')
            ->where('di.dateFinMax >= :ceJour')
            ->setParameter('ceJour', (new \DateTime())->setTime(0, 0, 0, 0))
            ->join('di.composant', 'c')
            ->andWhere('c.id IN (:composants)')
            ->setParameter('composants', $composantId)
            ->leftJoin('c.domaine', 'd')
        ;

        return $qb->getQuery()->getResult();
    }
}
