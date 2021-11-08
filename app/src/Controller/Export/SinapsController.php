<?php


namespace App\Controller\Export;

use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatTerminee;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SinapsController extends AbstractController
{
    /* Age de rétention maximum du cache en secondes (1h) */
    const CACHING_DURATION = 3600;
    /* Format des dates utilisés pour les exports (ISO 8601) */
    const EXPORT_DATE_FORMAT = 'c';
    /* Timezone des dates fournies dans les exports (UTC) */
    const EXPORT_DATE_TIMEZONE = 'utc';
    /* Format d'export par défaut des listes et tableaux */
    const EXPORT_LIST_FORMAT = 'array';
    /* Caractère utilisé pour formater les listes dans les exports */
    const EXPORT_LIST_SEPARATOR = ', ';

    /** @var DateTime $debutRecherche */
    private $debutRecherche = null;
    /** @var DateTime $finRecherche */
    private $finRecherche = null;

    /**
     * Retourne un json des interventions programmées pour Sinaps
     * @Route(
     *     "/export/sinaps/{debutRecherche}/{finRecherche?}",
     *     methods={"GET"},
     *     name="export-sinaps",
     *     requirements={
     *          "debutRecherche"="\d{4}-\d{2}-\d{2}",
     *          "finRecherche"="\d{4}-\d{2}-\d{2}"
     *     }
     * )
     *
     * @param Request       $request
     * @param DateTime      $debutRecherche
     * @param DateTime|null $finRecherche
     *
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function export(Request $request, DateTime $debutRecherche, ?DateTime $finRecherche): JsonResponse
    {
        // Si on est pas admin, on doit avoir au moins une Ip autorisée
        //  Pour une utilisation derrière un proxy, il faut définir TRUSTED_PROXIES (cf. .env),
        //      cf: https://symfony.com/doc/current/create_framework/http_foundation.html et
        //          https://symfony.com/doc/current/deployment/proxies.html
        if (!$this->isGranted(Service::ROLE_ADMIN)) {
            $allowedIps = explode(' ', trim($this->getParameter('allowed_external_services_ips')));
            if (!empty($allowedIps) && !empty($allowedIps[0]) && !in_array($request->getClientIp(), $allowedIps)) {
                throw new AccessDeniedHttpException('Vous ne pouvez pas accéder à ce flux.');
            }
        }

        // On récupère les dates de début et de fin pour la restitution
        if ($finRecherche === null) {
            $finRecherche = clone $debutRecherche;
        }

        // On met le temps 23h59m59s à la date de fin
        $finRecherche->setTime(23, 59, 59);

        // On met à jour les informations dans le contrôleur (pour pouvoir les récupérer pour traiter l'export)
        $this->debutRecherche = $debutRecherche;
        $this->finRecherche = $finRecherche;

        // Utilise le cache symfony pour mémoriser la structure de la réponse pour un délai fourni par CACHING_DURATION
        //   Pour info ce fichier est stocké dans le répertoire système du conteneur (/tmp/symfony-cache/)
        $cache = new FilesystemAdapter();
        $data = $cache->get(
            'export-sinaps-'.$debutRecherche->format('Ymd').'-'.$finRecherche->format('Ymd'),
            \Closure::fromCallable([$this, 'computeExportResponse'])
        );

        // Configure le cache client
        $jsonResponse = new JsonResponse($data['reponse']);
        $jsonResponse->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true'); // le mécanisme de session de symfony vérouille par défaut les directives de caching (notion de cache privé / public)
        $dateExpiration = $data['date_expiration'];
        $maxAge = abs($dateExpiration->getTimestamp() - time());
        $jsonResponse->setPublic();
        $jsonResponse->setMaxAge($maxAge);
        $jsonResponse->setExpires($dateExpiration);
        return $jsonResponse;
    }

    /**
     * Génère la réponse sous forme d'un tableau, ainsi que la date d'expiration du résultat obtenu
     *
     * @param ItemInterface $item
     *
     * @return array
     * @throws \Exception
     */
    private function computeExportResponse(ItemInterface $item): array
    {
        // Défini la durée de cache pour cette entrée
        $item->expiresAfter(self::CACHING_DURATION);

        // Définition de la liste des status des demandes sélectionnées
        $statusRecherches = [
            EtatAccordee::class,
            EtatAnalyseEnCours::class,
            EtatAnnulee::class,
            EtatConsultationEnCours::class,
            EtatConsultationEnCoursCdb::class,
            EtatInstruite::class,
            EtatInterventionEchouee::class,
            EtatInterventionEnCours::class,
            EtatInterventionReussie::class,
            EtatRefusee::class,
            EtatRenvoyee::class,
            EtatSaisirRealise::class,
            EtatTerminee::class,
        ];

        // Récupère la liste des demandes sélectionnées
        $demandeInterventionRepository = $this->getDoctrine()->getRepository(DemandeIntervention::class);
        $demandeInterventions = $demandeInterventionRepository->createQueryBuilder('di')
            ->addSelect('c', 'e', 'm', 's')
            ->join('di.composant', 'c')
            ->leftJoin('c.equipe', 'e')
            ->join('di.motifIntervention', 'm')
            ->join('di.services', 's')
            ->join('di.impacts', 'i')
            ->join('i.nature', 'n')
            ->where('di.dateDebut BETWEEN :debutPeriode AND :finPeriode')
            ->setParameter('debutPeriode', $this->debutRecherche)
            ->setParameter('finPeriode', $this->finRecherche)
            ->andWhere('di.status IN (:status)')
            ->setParameter('status', $statusRecherches)
            ->orderBy("di.dateDebut", 'ASC')
            ->addOrderBy('di.numero', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        // Construit la réponse
        $reponse = [
            'date'          => self::formatDate(new \DateTime('now')),
            'interventions' => []
        ];

        /** @var DemandeIntervention $demandeIntervention */
        foreach ($demandeInterventions as $demandeIntervention) {
            // Génère la liste des csis
            $csis = [];
            foreach ($demandeIntervention->getServices() as $annuaire) {
                $csis[] = $annuaire->getService()->getLabel();
            }
            // Récupère les données des impacts
            $impacts = [];
            $globalListeApplis = [];
            $globalListeAppliIds = [];
            foreach ($demandeIntervention->getImpacts() as $impact) {
                $impactListeApplis = [];
                $impactListeAppliIds = [];
                foreach ($impact->getComposants() as $composant) {
                    $composantLabel = $composant->getLabel();
                    $composantId = $composant->getId();
                    $impactListeApplis[$composant->getLabel()] = $composantLabel;
                    $impactListeAppliIds[$composantId] = $composantId;
                    $globalListeApplis[] = $composantLabel;
                    $globalListeAppliIds[] = $composantId;
                }
                $impacts[] = [
                    'type_id'               => $impact->getNature()->getId(),
                    'type'                  => $impact->getNature()->getLabel(),
                    'commentaire'           => self::formatText($impact->getCommentaire()),
                    'date_debut'            => self::formatDate($impact->getDateDebut()),
                    'date_fin_min'          => self::formatDate($impact->getDateFinMini()),
                    'date_fin_max'          => self::formatDate($impact->getDateFinMax()),
                    'applications'          => self::formatList($impactListeApplis),
                ];
            }
            // Génère la structure d'une intervention
            $composantDemande = $demandeIntervention->getComposant();
            $reponseDemande = [
                'numero'                    => $demandeIntervention->getNumero(),
                'url'                       => $this->generateUrl(
                    'visualisation-demande-exterieure',
                    [ 'numero' => $demandeIntervention->getNumero() ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'application'               => $composantDemande->getLabel(),
                'demandeur'                 => $demandeIntervention->getDemandePar()->getLabel(),
                'motif'                     => $demandeIntervention->getMotifIntervention()->getLabel(),
                'detail'                    => self::formatText($demandeIntervention->getDescription()),
                'exploitants'               => self::formatList($csis),
                'date_debut'                => self::formatDate($demandeIntervention->getDateDebut()),
                'date_fin_min'              => self::formatDate($demandeIntervention->getDateFinMini()),
                'date_fin_max'              => self::formatDate($demandeIntervention->getDateFinMax()),
                'duree_retour_arriere'      => $demandeIntervention->getDureeRetourArriere(),
                'statut_id'                 => str_replace('App\\Workflow\\Etats\\', '', $demandeIntervention->getStatus()),
                'statut'                    => $demandeIntervention->getStatusLibelle(),
                'impacts'                   => $impacts
            ];
            $reponse['interventions'][] = $reponseDemande;
        }

        // Retourne la réponse générée
        return [
            'date_expiration'   => (new \DateTime())->add(new \DateInterval('PT' . self::CACHING_DURATION . 'S')),
            'reponse'           => $reponse,
        ];
    }

    /**
     * Formate une date au format demandé pour les exports
     * @param \DateTimeInterface $dateTime
     * @return string|null
     */
    private static function formatDate(?\DateTimeInterface $dateTime) : ?string
    {
        if (null !== $dateTime) {
            return $dateTime
                ->setTimeZone(new \DateTimeZone(self::EXPORT_DATE_TIMEZONE))
                ->format(self::EXPORT_DATE_FORMAT);
        }
        return $dateTime;
    }

    /**
     * Formate un texte (possiblement multi-lignes) pour les exports
     * @param string $text
     * @return string
     */
    private static function formatText(?string $text) : string
    {
        return trim(str_replace(["\r", "\n"], ' ', $text));
    }

    /**
     * Formate une liste pour les exports
     *      (on en profite pour dédoublonner, et trier les élements du tableau)
     * @param string[] $array
     * @param string $format
     * @return array|string
     */
    private static function formatList(array $array, string $format = self::EXPORT_LIST_FORMAT)
    {
        $array = array_unique($array);
        sort($array);
        if ('string' === $format) {
            return trim(implode(self::EXPORT_LIST_SEPARATOR, $array));
        }
        return $array;
    }
}
