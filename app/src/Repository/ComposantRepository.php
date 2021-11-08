<?php

namespace App\Repository;

use App\Entity\Composant;
use App\Entity\Meteo\Composant as MeteoComposant;
use App\Entity\Meteo\Evenement;
use App\Entity\Meteo\Publication;
use App\Entity\References\Mission;
use App\Entity\Service;
use App\Utils\CalculatriceDisponibilite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Composant|null find($id, $lockMode = null, $lockVersion = null)
 * @method Composant|null findOneBy(array $criteria, array $orderBy = null)
 * @method Composant[]    findAll()
 * @method Composant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComposantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Composant::class);
    }

    /**
     * recherche d'un composant par son libellé
     *
     * @param string|null $labelSearch
     * @param bool        $avecArchive (default: false)
     *
     * @return array[]
     */
    public function searchByLabel(?string $labelSearch, bool $avecArchive = false): array
    {
        $labelSearch = '%' . str_replace(['_', '%'], ['\\_', '\\%'], $labelSearch) . '%';

        $query = $this->createQueryBuilder('c')
            ->select(['c.id', 'c.label'])
            ->where('UPPER(c.label) LIKE UPPER(:labelSearch)')
                ->setParameter('labelSearch', $labelSearch)
            ->orderBy('c.label', 'asc');

        if (!$avecArchive) {
            $query->andWhere('c.archiveLe IS NULL');
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Recherche d'un composant par son libellé
     *
     * @param string|null $labelSearch
     *
     * @return array[]
     */
    public function multiSelectSearchByLabel(?string $labelSearch): array
    {
        // On se prépare
        $resultats = [];

        // On lance notre requête
        $query = $this->searchByLabel($labelSearch);

        // On ajoute une entête pour l'affichage des résultats du champ
        $resultats[] = [
            'label' => 'Label',
        ];

        // On renvoi les résultats
        return array_merge([ ['label' => 'Label'] ], $query);
    }

    /**
     * recherche des composants
     * @return Composant[]
     */
    public function listeComposants(array $tableauRecherche = null): array
    {
        $query = $this->createQueryBuilder('c')
            ->addSelect(['eq', 'p', 'ex', 'd', 'u'])
            ->leftJoin('c.equipe', 'eq')
            ->leftJoin('c.pilote', 'p')
            ->leftJoin('c.exploitant', 'ex')
            ->leftJoin('c.domaine', 'd')
            ->leftJoin('c.usager', 'u')
            ;

        if ($tableauRecherche === null) {
            $query->andWhere('c.archiveLe IS NULL');
        } else {
            if (!empty($tableauRecherche['label'])) {
                $query->where('lower(c.label) LIKE :label');
                $query->setParameter('label', '%'.mb_strtolower($tableauRecherche['label']).'%');
            }
            if (!empty($tableauRecherche['equipeId'])) {
                $query->andWhere('c.equipe = :equipeId');
                $query->setParameter('equipeId', $tableauRecherche['equipeId']);
            }
            if (!empty($tableauRecherche['piloteId'])) {
                $query->andWhere('c.pilote = :piloteId');
                $query->setParameter('piloteId', $tableauRecherche['piloteId']);
            }
            if (!empty($tableauRecherche['piloteTitulaireOuSuppleantId'])) {
                $query->andWhere('c.pilote = :piloteId OR c.piloteSuppleant = :piloteId');
                $query->setParameter('piloteId', $tableauRecherche['piloteTitulaireOuSuppleantId']);
            }
            if (!empty($tableauRecherche['exploitantId'])) {
                $query->andWhere('c.exploitant = :exploitantId');
                $query->setParameter('exploitantId', $tableauRecherche['exploitantId']);
            }
            if (!empty($tableauRecherche['usagerId'])) {
                $query->andWhere('c.usager = :usagerId');
                $query->setParameter('usagerId', $tableauRecherche['usagerId']);
            }
            if (!empty($tableauRecherche['domaineId'])) {
                $query->andWhere('c.domaine = :domaineId');
                $query->setParameter('domaineId', $tableauRecherche['domaineId']);
            }
            if (!empty($tableauRecherche['bureauRattachementId'])) {
                $query->andWhere('c.bureauRattachement = :bureauRattachementId');
                $query->setParameter('bureauRattachementId', $tableauRecherche['bureauRattachementId']);
            }
            if (!array_key_exists('isArchived', $tableauRecherche) || !$tableauRecherche['isArchived']) {
                $query->andWhere('c.archiveLe IS NULL');
            }
        }
        $query->orderby('c.label', 'ASC');

        return $query->getQuery()->getResult();
    }

    /**
     * Récupère toutes les valeurs sous forme d'un tableau avec comme clé, la clé primaire de l'entrée.
     * @return array
     */
    public function findAllInArray(): array
    {
        return $this->createQueryBuilder("c", "c.id")->getQuery()->getArrayResult();
    }

    /**
    * recherche si libellé composant déjà utilisé avec casse différente
    * @return Composant[]
    */
    public function libelleComposantDejaUtilise(array $champs): array
    {
        return $this->createQueryBuilder('c')
            ->where('UPPER(c.label) = :val')
            ->andWhere('c.archiveLe IS NULL')
            ->setParameter('val', strtoupper($champs['label']))
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Fonction permettant de retourner les composants dont est identifié le service passé en paramètre en tant que
     * MOE ou MOE Délégué
     * @param Service $service
     * @return array
     */
    public function composantsMoeService(Service $service): array
    {
        // Récupération de l'entity manager
        $em = $this->getEntityManager();

        // On récupère les missions MOE et MOE Délégué
        $moeMissions = $em->getRepository(Mission::class)
            ->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.label like :moe')
            ->setParameter(':moe', '%MOE%')
            ->getQuery()->getResult();

        // On récupère les composants que l'on renvoi ensuite
        return $em->createQuery(
            'SELECT c
            FROM App\Entity\Composant c
            JOIN c.annuaire a
            WHERE
                a.mission IN (:missions) AND
                a.service = :service AND
                c.archiveLe IS NULL
            ORDER by c.label ASC'
        )
        ->setParameter('missions', $moeMissions)
        ->setParameter('service', $service)
        ->getResult();
    }

    /**
     * Chargement d'un composant avec ces flux entrants préchargés
     * @param int $composantId
     * @return Composant|null
     */
    public function findAvecFluxEntrants(int $composantId): ?Composant
    {
        return $this->createQueryBuilder('c')
            ->addSelect(['fe'])
            ->leftJoin('c.impactesParComposants', 'fe')
            ->where('c.id = :id')
            ->setParameter('id', $composantId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Chargement d'un composant avec ces flux sortants préchargés
     * @param int $composantId
     * @return Composant|null
     */
    public function findAvecFluxSortants(int $composantId): ?Composant
    {
        return $this->createQueryBuilder('c')
            ->addSelect(['fs'])
            ->leftJoin('c.composantsImpactes', 'fs')
            ->where('c.id = :id')
            ->setParameter('id', $composantId)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * liste des libellés de composants concernés par un impact
     * @return array[]
     */
    public function composantsImpactes($idImpact): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT c.label
            FROM App\Entity\Demande\Impact i
            JOIN i.composants c
            WHERE i.id = :impact'
        )->setParameter('impact', $idImpact);

        return $query->getResult();
    }

    /**
     * Listing des services avec comptage des composants pour une mission donnée
     * @param Mission $mission
     * @return array
     */
    public function servicesComposantsParMission(Mission $mission) : array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT s.id as id, s.label as label, COUNT(DISTINCT(c)) as nbComposants
            FROM App\Entity\Composant\Annuaire a
            LEFT JOIN a.mission m
            LEFT JOIN a.service s
            LEFT JOIN a.composant c
            WHERE
                a.supprimeLe IS NULL AND
                c.archiveLe IS NULL AND
                m.supprimeLe IS NULL AND
                m.id = :mission
            GROUP BY id, label
            ORDER BY label ASC'
        );
        $query->setParameter('mission', $mission);
        return $query->getResult();
    }

    /**
     * Récupération d'un composant et de tout ce qui est nécessaire pour la restitution.
     * @param int $id
     * @return Composant|null
     */
    public function restitutionComposant(int $id): ?Composant
    {
        $query = $this->createQueryBuilder('c')
            ->select(['c', 'u', 'd', 'se', 'e', 'p', 'ps', 't', 'br', 'a', 'ase', 'ami', 'pu'])
            ->leftJoin('c.usager', 'u')
            ->leftJoin('c.domaine', 'd')
            ->leftJoin('c.exploitant', 'se')
            ->leftJoin('c.equipe', 'e')
            ->leftJoin('c.pilote', 'p')
            ->leftJoin('c.piloteSuppleant', 'ps')
            ->leftJoin('c.typeElement', 't')
            ->leftJoin('c.bureauRattachement', 'br')
            ->leftJoin('c.annuaire', 'a')
            ->leftJoin('a.service', 'ase')
            ->leftJoin('a.mission', 'ami')
            ->leftJoin('c.plagesUtilisateur', 'pu')
            ->where('c.id = :id')
            ->setParameter('id', $id)
        ;

        return $query->getQuery()->getOneOrNullResult();
    }

    public function listeChoixIntituleeUtilisateur(): array
    {
        $query = $this->createQueryBuilder('c')
            ->select('c.intitulePlageUtilisateur')
            ->distinct()
            ->getQuery()
            ->getArrayResult();

        $resultat = array_column($query, 'intitulePlageUtilisateur');
        $resultat = array_combine($resultat, $resultat);

        return $resultat;
    }

    /**
     * Permet de lister les composants d'un service exploitant.
     *
     * @param Service $serviceExploitant
     * @return Composant[]
     */
    public function listeComposantsParExploitant(Service $serviceExploitant): array
    {
        // On définie une variable permettant de filtrer les services a utilisé
        $filtreServices = [$serviceExploitant->getId()];

        // Si l'utilisateur courant est une structure principale, alors on récupère les services associés
        //  (afin de les ajouter de le filtrage des composants)
        if ($serviceExploitant->getEstStructureRattachement()) {
            $tmpServices = $this->getEntityManager()->getRepository(Service::class)->findBy([
                'structurePrincipale' => $serviceExploitant,
                'supprimeLe' => null,
            ]);
            /** @var Service $s */
            foreach ($tmpServices as $s) {
                $filtreServices[] = $s->getId();
            }
        }

        // On renvoi les composants associés aux services
        return $this->createQueryBuilder('c')
            ->select(['c', 'a', 'm', 'pu'])
            ->join('c.annuaire', 'a')
            ->join('a.mission', 'm')
            ->leftJoin('c.plagesUtilisateur', 'pu')
            ->where('a.service IN (:services)')
            ->andWhere('c.archiveLe is null')
            ->andWhere('m.label IN (:missions)')
            ->setParameter('services', $filtreServices)
            ->setParameter('missions', [
                'ES Exploitant Système',
                'EA Exploitant Applicatif',
                'MOA',
                'MOA Associée',
                'MOE',
                'MOE Déléguée',
                'ESI hebergeur',
                'Scrum master',
                'Product Owner',
                'Dev Team',
                'Equipe OPS',
            ])
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Fonction permettant de récupérer les indices ainsi que le taux de disponibilité des composants passés en
     * paramètre et pour une période donnée.
     *
     * @param array     $idsComposants
     * @param \DateTime $periodeDebut
     *
     * @return array
     */
    public function indicesMeteoComposants(array $idsComposants, \DateTime $periodeDebut) : array
    {
        // On récupère l'entity manager de doctrine
        $em = $this->getEntityManager();
        $resultats = [];

        // On défini notre range de période
        $periodeDebut = $periodeDebut->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
        $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTime(23, 59, 59);

        // On regarde en base si la période est déjà publiée en base
        $publiee = $this->getEntityManager()->getRepository(Publication::class)->periodeEstPubliee($periodeDebut, $periodeFin);

        // Si la plage est publiée
        if ($publiee) {
            // On va chercher les éléments dans MeteoComposant
            $cacheMeteoComposants = $em->createQuery(
                'SELECT mc, c
                FROM App\Entity\Meteo\Composant mc
                JOIN mc.composant c
                WHERE
                    mc.periodeDebut = :periodeDebut AND
                    mc.periodeFin = :periodeFin AND
                    mc.composant IN (:idsComposants) AND
                    c.meteoActive = true
                '
            )
            ->setParameter('periodeDebut', $periodeDebut)
            ->setParameter('periodeFin', $periodeFin)
            ->setParameter('idsComposants', $idsComposants)
            ->getResult();

            // On va chercher les validations de météo des exploitants pour cette période
            $validationMeteoExploitants = [];
            $QueryValidationMeteo = $em->createQuery(
                'SELECT v
                FROM App\Entity\Meteo\Validation v
                WHERE v.periodeDebut = :periodeDebut'
            )
            ->setParameter('periodeDebut', $periodeDebut)
            ->getResult();
            foreach ($QueryValidationMeteo as $validation) {
                $validationMeteoExploitants[] = $validation->getExploitant()->getId();
            }

            /** @var MeteoComposant $meteoComposant */
            foreach ($cacheMeteoComposants as $meteoComposant) {
                $composant = $meteoComposant->getComposant();
                $isValidationParExploitant = $composant->getExploitant() === null || in_array($composant->getExploitant()->getId(), $validationMeteoExploitants);
                $resultats[$composant->getId()] = [
                    'id' => $composant->getId(),
                    'label' => $composant->getLabel(),
                    'indice' => $isValidationParExploitant ? $meteoComposant->getMeteo() : MeteoComposant::NC,
                    'disponibilite' => $meteoComposant->getDisponibilite()
                ];
            }

            // On va chercher les autres composants pour afficher un grand soleil par défaut.
            //   On ne stocke que les composants ayant eu des évènements dans `meteo_composant`.
            $composants = $this->getEntityManager()->getRepository(Composant::class)->findBy([
                'id' => $idsComposants
            ]);
            foreach ($composants as $composant) {
                if (!isset($resultats[$composant->getId()]) && $composant->getMeteoActive()) {
                    $isValidationParExploitant = $composant->getExploitant() === null || in_array($composant->getExploitant()->getId(), $validationMeteoExploitants);
                    $resultats[$composant->getId()] = [
                        'id' => $composant->getId(),
                        'label' => $composant->getLabel(),
                        'indice' => $isValidationParExploitant ? MeteoComposant::ENSOLEILLE : MeteoComposant::NC,
                        'disponibilite' => null
                    ];
                }
            }

            // Enfin on retri notre résultat par label
            uasort($resultats, function ($a, $b) {
                return ($a['label'] < $b['label']) ? -1 : 1;
            });

            return $resultats;

        // Sinon, on va calculer les indices et dispo en temps réels
        } else {
            // Liste des impacts qui sont à prendre en compte dans le calcul de dispo
            $impactsPrisEnCompte = [
                'Accès impossible',
                'Indisponibilité programmée',
                'Indisponibilité totale'
            ];

            // On initialise quelques variables importantes
            $calculatricesDisponibilites = [];
            $evenementsTypesParComposants = [];

            // On va chercher tous nos composants
            $composants = $em->createQuery(
                'SELECT c, pu, us
                FROM App\Entity\Composant c
                LEFT JOIN c.plagesUtilisateur pu
                LEFT JOIN c.usager us
                WHERE
                    c.id IN (:composants) AND
                    c.meteoActive = true AND
                    c.archiveLe IS NULL
                ORDER BY c.label ASC'
            )
            ->setParameter('composants', $idsComposants)
            ->getResult();

            // On va chercher les évènements dans MeteoEvenement
            $QueryEvenementComposant = $em->createQuery(
                'SELECT e, c, i, t
                FROM App\Entity\Meteo\Evenement e
                JOIN e.composant c
                JOIN e.impact i
                JOIN e.typeOperation t
                WHERE
                    e.debut <= :fin AND e.fin >= :debut AND
                    e.composant IN (:composants) AND
                    c.meteoActive = true
                ORDER BY c.label ASC'
            )
            ->setParameter('debut', $periodeDebut)
            ->setParameter('fin', $periodeFin)
            ->setParameter('composants', $composants)
            ->getResult();

            // On va chercher les validations de météo des exploitants
            $validationsPublicationMeteo = [];
            $QueryValidationMeteo = $em->createQuery(
                'SELECT v
                FROM App\Entity\Meteo\Validation v
                WHERE v.periodeDebut = :periodeDebut'
            )
            ->setParameter('periodeDebut', $periodeDebut)
            ->getResult();
            foreach ($QueryValidationMeteo as $validation) {
                $validationsPublicationMeteo[$validation->getExploitant()->getId()] = $validation->getAjouteLe();
            }

            // On crée nos calculateur de disponible pour chaque composants avec la plage utilisateur
            /** @var Composant $composant */
            foreach ($composants as $composant) {
                // On crée notre calculatrice par rapport au composant ainsi qu'à la période demandée (avec les plages utilisateurs en fonction de l'usage !)
                $calculatricesDisponibilites[$composant->getId()] = new CalculatriceDisponibilite($periodeDebut, $periodeFin, $composant->getPlagesUtilisateurViaUsage());

                // On initialise notre tableau de comptage des évènements en fonction du type et du composant
                $evenementsTypesParComposants[$composant->getId()] = [
                    'incidents' => 0,
                    'retard_majeur' => 0
                ];
            }

            // On parcourt nos évènements que l'on place dans un tableau indexé au composant précis
            /** @var Evenement $evenementMeteo */
            foreach ($QueryEvenementComposant as $evenementMeteo) {
                // On stocke l'id du composant pour une utilisation future
                $idComposant = $evenementMeteo->getComposant()->getId();

                // On ajoute l'indisponibilité au calculateur de dispo du composant et qu'il est a prendre en compte
                if (in_array($evenementMeteo->getImpact()->getLabel(), $impactsPrisEnCompte)) {
                    $calculatricesDisponibilites[$idComposant]->ajoutIndisponibilite($evenementMeteo->getDebut(), $evenementMeteo->getFin());
                }

                // Si l'évènement est de type "Retard majeur dans la mise à jour des données"
                if ($evenementMeteo->getImpact()->getLabel() === "Retard majeur dans la mise à jour des données") {
                    $evenementsTypesParComposants[$idComposant]['retard_majeur']++;
                }

                // Si l'évènement est un incident
                if ($evenementMeteo->getTypeOperation()->getLabel() === "Incident") {
                    $evenementsTypesParComposants[$idComposant]['incidents']++;
                }
            }

            // On parcourt une nouvelle fois nos composant pour déterminer les indices
            /** @var Composant $composant */
            foreach ($composants as $composant) {
                // Non Communiqué : Par défaut global
                $indice = MeteoComposant::NC;
                $disponibilite = null;

                // Si l'exploitant à donné sa validation de publication
                if (!$composant->getExploitant() || isset($validationsPublicationMeteo[$composant->getExploitant()->getId()])) {
                    // Soleil : Par défaut si publication validée
                    $indice = MeteoComposant::ENSOLEILLE;
                    $disponibilite = $calculatricesDisponibilites[$composant->getId()]->getTauxDisponibilite();

                    // Orageux : Si le taux de disponibilité est inférieur à 80%
                    if ($disponibilite < 80) {
                        $indice = MeteoComposant::ORAGEUX;

                        // Nuageux : Si 1 Retard majeur OU Si plus ou égal de 4 incidents OU Dispo entre 80 et 99,99
                    } elseif ($evenementsTypesParComposants[$composant->getId()]['retard_majeur'] > 0 ||
                        $evenementsTypesParComposants[$composant->getId()]['incidents'] >= 4 ||
                        $disponibilite >= 80 && $disponibilite < 100
                    ) {
                        $indice = MeteoComposant::NUAGEUX;
                    }
                }

                // On met en forme les résultats
                $resultats[$composant->getId()] = [
                    'id' => $composant->getId(),
                    'label' => $composant->getLabel(),
                    'indice' => $indice,
                    'disponibilite' => $disponibilite
                ];
            }

            // On renvoie les résultats
            return $resultats;
        }
    }

    /**
     * Renvoie les composants filtrés pour le calcul du taux d'indisponibilité.
     */
    public function getComposantIndisponibilites(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('pu', 'refu')
            ->leftJoin('c.plagesUtilisateur', 'pu')     // pour getPlagesUtilisateurViaUsage
            ->leftJoin('c.usager', 'refu')              // utilisé dans getPlagesUtilisateurViaUsage (en interne)
            ->where('c.archiveLe IS NULL')
            ->orderBy('c.label', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        // Ajoute les filtres définis par l'utilisateur
        if (!empty($filtres['equipe'])) {
            $qb->andWhere('c.equipe = :equipeId')
                ->setParameter('equipeId', $filtres['equipe']);
        }
        if (!empty($filtres['pilote'])) {
            $qb->andWhere('c.pilote = :piloteId')
                ->setParameter('piloteId', $filtres['pilote']);
        }
        if (!empty($filtres['exploitant'])) {
            $qb->andWhere('c.exploitant = :exploitantId')
                ->setParameter('exploitantId', $filtres['exploitant']);
        }

        // retourne le résultat
        return $qb->getQuery()->getResult();
    }

    /*
     * Renvoie les taux de disponibilités au cours de la période pour les composants fournis.
     * @param string $source
     * @param \DateTimeInterface $debut
     * @param \DateTimeInterface $fin
     * @param Composant[] $composants
     * @return array
     */
    public function getTauxIndisponibilites(string $source, \DateTimeInterface $debut, \DateTimeInterface $fin, array $composants): array
    {
        // Liste des impacts qui sont à prendre en compte dans le calcul de dispo
        $impactsPrisEnCompte = [
            'Accès impossible',
            'Indisponibilité programmée',
            'Indisponibilité totale'
        ];

        //
        $reponse = [
            'periode' => [
                'label' => 'Du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
                'debut' => $debut->format('d/m/Y H:i:s'),
                'fin'   => $fin->format('d/m/Y H:i:s'),
            ]
        ];

        // On crée nos calculateur de disponible pour chaque composants avec la plage utilisateur
        $calculatricesDisponibilites = [];
        foreach ($composants as $composant) {
            $calculatricesDisponibilites[$composant->getId()] = new CalculatriceDisponibilite($debut, $fin, $composant->getPlagesUtilisateurViaUsage());
        }

        if ($source == 'evenements') {
            // On va chercher les évènements dans MeteoEvenement
            $QueryEvenementComposant = $this->getEntityManager()->createQuery(
                'SELECT e, c, i, t
                FROM App\Entity\Meteo\Evenement e
                JOIN e.composant c
                JOIN e.impact i
                JOIN e.typeOperation t
                WHERE
                    (
                        (e.debut >= :debut AND e.debut <= :fin) OR
                        (e.fin >= :debut AND e.fin <= :fin)
                    ) AND
                    e.composant IN (:composants) AND
                    c.meteoActive = true
                ORDER BY c.label ASC'
            )
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->setParameter('composants', $composants)
            ->getResult();

            // On parcourt nos évènements que l'on place dans un tableau indexé au composant précis
            /** @var Evenement $evenementMeteo */
            foreach ($QueryEvenementComposant as $evenementMeteo) {
                // On stocke l'id du composant pour une utilisation future
                $idComposant = $evenementMeteo->getComposant()->getId();
                // On ajoute l'indisponibilité au calculateur de dispo du composant et qu'il est a prendre en compte
                if (in_array($evenementMeteo->getImpact()->getLabel(), $impactsPrisEnCompte)) {
                    $calculatricesDisponibilites[$idComposant]->ajoutIndisponibilite($evenementMeteo->getDebut(), $evenementMeteo->getFin());
                }
            }
        } else {
            // On va chercher dans les interventions et les impacts réels
            $queryImpactReelComposant = $this->getEntityManager()->createQuery(
                'SELECT ir, c, n
                FROM App\Entity\Demande\ImpactReel ir
                JOIN ir.composants c
                JOIN ir.nature n
                WHERE
                    (
                        (ir.dateDebut >= :debut AND ir.dateDebut <= :fin) OR
                        (ir.dateFin >= :debut AND ir.dateFin <= :fin)
                    ) AND
                    c.id IN (:composants)
                ORDER BY c.label ASC'
            )
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->setParameter('composants', $composants)
            ->getResult();

            // On parcourt nos impacts réels que l'on place dans un tableau indexé au composant précis
            /** @var Evenement $evenementMeteo */
            foreach ($queryImpactReelComposant as $impactReel) {
                foreach ($impactReel->getComposants() as $composant) {
                    // On stocke l'id du composant pour une utilisation future
                    $idComposant = $composant->getId();
                    // On ajoute l'indisponibilité au calculateur de dispo du composant et qu'il est a prendre en compte
                    if (in_array($impactReel->getNature()->getLabel(), $impactsPrisEnCompte)) {
                        $calculatricesDisponibilites[$idComposant]->ajoutIndisponibilite($impactReel->getDateDebut(), $impactReel->getDateFin());
                    }
                }
            }
        }

        // On parcourt une nouvelle fois nos composant pour déterminer les indices
        /** @var Composant $composant */
        foreach ($composants as $composant) {
            $composantId = $composant->getId();
            // Si le composant a la météo activée et que la source est évènements météo
            if (!$composant->getMeteoActive() && $source === 'evenements') {
                // On met en forme les résultats
                $reponse['indisponibilite'][$composantId] = '';
            } else {
                $reponse['indisponibilite'][$composantId] = $calculatricesDisponibilites[$composantId]->getTauxIndisponibilite();
            }
        }

        // On retourne le tableau obtenu
        return $reponse;
    }

    /**
     * Retourne les météo publiées par composant et année.
     *
     * @param Composant $composant
     * @param Int $annee
     * @return array[]
     */
    public function meteoAnnuelle(Composant $composant, int $annee): array
    {
        // Initialisation des dates de périodes
        $debutPeriode = new \DateTime($annee.'-01-01');
        $finPeriode = new \DateTime($annee.'-12-31');

        // Pour chaque période de publication on cherche les semaines météos du composant
        $meteoComposantsQuery = $this->getEntityManager()->createQuery(
            'SELECT mc, c, me
            FROM App\Entity\Meteo\Composant mc
            JOIN mc.composant c
            LEFT JOIN c.evenementsMeteo me
            WHERE
                mc.composant = :composant AND
                mc.periodeDebut <= :periodeFin AND
                mc.periodeFin >= :periodeDebut AND
                me.debut <= :periodeFin AND
                me.fin >= :periodeDebut
            ORDER BY me.debut ASC, mc.periodeDebut ASC
            '
        )
            ->setParameter('composant', $composant)
            ->setParameter('periodeDebut', $debutPeriode)
            ->setParameter('periodeFin', $finPeriode)
            ->getResult();

        // En renvoi le résultat
        return $meteoComposantsQuery;
    }

    /**
     * Retourne une liste de composant comportant au moins une version de carte d'identité
     */
    public function getComposantsAvecCarteIdentite(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.carteIdentites', 'ci')
            ->where('c.archiveLe IS NULL')
            ->orderBy('LOWER(c.label)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la liste des composants dont la météo est active en fonction de l'id de l'exploitant
     * (ou id de l'exploitant en structure principale)
     *
     * @param Service|null $exploitant
     *
     * @return array
     */
    public function getComposantsMeteoExploitant(?Service $exploitant) : array
    {
        $query = $this->createQueryBuilder('c')
            ->where('c.archiveLe IS NULL')
            ->andWhere('c.meteoActive = true')
            ->orderBy('LOWER(c.label)', 'ASC');

        if ($exploitant !== null) {
            $query->join('c.exploitant', 'cex')
                ->andWhere('(c.exploitant = :exploitant OR cex.structurePrincipale = :exploitant)')
                ->setParameter('exploitant', $exploitant);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Permet de retourner un composant avec les services annuaires.
     *
     * @param int $composantId
     *
     * @return Composant
     */
    public function findAvecServicesAnnuaires(int $composantId): Composant
    {
        return $this->createQueryBuilder('c')
            ->addSelect(['ca', 'cas', 'cam'])
            ->where('c.id = :id')->setParameter(':id', $composantId)
            ->leftJoin('c.annuaire', 'ca')
            ->leftJoin('ca.service', 'cas')
            ->leftJoin('ca.mission', 'cam')
            ->getQuery()
            ->getSingleResult();
    }
}
