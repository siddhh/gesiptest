<?php

namespace App\Controller\Export;

use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatTerminee;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;

class IsacController extends AbstractController
{

    /* Age de rétention maximum du cache (en secondes) */
    const CACHING_DURATION = 7200;
    /* Format des dates utilisés pour les exports (ISO 8601) */
    const EXPORT_DATE_FORMAT = 'c';
    /* Timezone des dates fournies dans les exports (UTC) */
    const EXPORT_DATE_TIMEZONE = 'utc';
    /* Format d'export par défaut des listes et tableaux */
    const EXPORT_LIST_FORMAT = 'array';
    /* Caractère utilisé pour formater les listes dans les exports */
    const EXPORT_LIST_SEPARATOR = ', ';

    /**
     * Retourne un json des futurs interventions programmées pour Isac
     * @Route("/export/isac", methods={"GET"}, name="export-isac")
     * @return JsonResponse
     */
    public function exportIsac(Request $request): JsonResponse
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

        // Utilise le cache symfony pour mémoriser la structure de la réponse pour un délai fourni par CACHING_DURATION
        //   Pour info ce fichier est stocké dans le répertoire système du conteneur (/tmp/symfony-cache/)
        $cache = new FilesystemAdapter();
        $data = $cache->get('export-isac', \Closure::fromCallable([$this, 'computeExportIsacResponse']));

        // Configure le cache client
        $jsonResponse = new JsonResponse($data['reponse']);
        $jsonResponse->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true'); // le mécanisme de session de symfony vérouille par défaut les directives de caching (notion de cache privé / public)
        $dateExpiration = $data['date_expiration'];
        $maxAge = abs($dateExpiration->getTimestamp() - time());
        $jsonResponse->setPublic();
        $jsonResponse->setMaxAge($maxAge);
        //$jsonResponse->setSharedMaxAge($maxAge);  // autorise les proxies entre le client et le serveur à jouer le rôle de cache de premier niveau
        $jsonResponse->setExpires($dateExpiration);
        return $jsonResponse;
    }

    /**
     * Génère la réponse sous forme d'un tableau, ainsi que la date d'expiration du résultat obtenu
     * @param ItemInterface $item
     * @return array
     */
    private function computeExportIsacResponse(ItemInterface $item): array
    {
        // Défini la durée de cache pour cette entrée
        $item->expiresAfter(self::CACHING_DURATION);
        // Définition de la liste des status des demandes sélectionnées
        $statusRecherches = [
            EtatAccordee::class,
            EtatInterventionEchouee::class,
            EtatInterventionEnCours::class,
            EtatInterventionReussie::class,
            EtatSaisirRealise::class,
            EtatTerminee::class,
        ];
        // Définition de la période des interventions sélectionnées
        $debutRecherche = (new \DateTime())->sub(new \DateInterval('P7D'));
        $finRecherche = new \DateTime();
        // Récupère la liste des demandes sélectionnées
        $demandeInterventionRepository = $this->getDoctrine()->getManager()->getRepository(DemandeIntervention::class);
        $demandeInterventions = $demandeInterventionRepository->createQueryBuilder('di')
            ->addSelect('c', 'e', 'm', 's')
            ->join('di.composant', 'c')
            ->leftJoin('c.equipe', 'e')
            ->join('di.motifIntervention', 'm')
            ->join('di.services', 's')
            ->join('di.impacts', 'i')
            ->join('i.nature', 'n')
            ->where('di.dateDebut BETWEEN :debutPeriode AND :finPeriode')
            ->setParameter('debutPeriode', $debutRecherche)
            ->setParameter('finPeriode', $finRecherche)
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
                    'type'                  => $impact->getNature()->getLabel(),
                    'commentaire'           => self::formatText($impact->getCommentaire()),
                    'date_debut'            => self::formatDate($impact->getDateDebut()),
                    'date_fin_max'          => self::formatDate($impact->getDateFinMax()),
                    'listeapplis'           => self::formatList($impactListeApplis),
                    'identifiants_applis'   => self::formatList($impactListeAppliIds),
                ];
            }
            // Génère la structure d'une intervention
            $composantDemande = $demandeIntervention->getComposant();
            $reponseDemande = [
                'numero'                    => $demandeIntervention->getNumero(),
                'brique'                    => empty($composantDemande->getCodeCarto()) ? null : $composantDemande->getCodeCarto(),
                'application'               => $composantDemande->getLabel(),
                'identifiant_application'   => $composantDemande->getId(),
                'service'                   => $demandeIntervention->getDemandePar()->getLabel(),
                'motif'                     => $demandeIntervention->getMotifIntervention()->getLabel(),
                'detail'                    => self::formatText($demandeIntervention->getDescription()),
                'csi'                       => self::formatList($csis),
                'libelle'                   => $demandeIntervention->getMotifIntervention()->getLabel(),
                'listeapplis'               => self::formatList($globalListeApplis),
                'identifiants_applis'       => self::formatList($globalListeAppliIds),
                'date_debut'                => self::formatDate($demandeIntervention->getDateDebut()),
                'date_fin'                  => self::formatDate($demandeIntervention->getDateFinMax()),
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
