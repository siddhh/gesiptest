<?php

namespace App\Controller\Ajax;

use App\Utils\Slimdown;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChangelogController extends AbstractController
{
    /** On défini quelques constantes */
    const VERSION_PAR_PAGE = 20;
    const VERSION_PROD = "prod";
    const VERSION_PREPROD = "preprod";

    /** @var HttpClientInterface $httpClient */
    private $httpClient;
    /** @var AdapterInterface $cache */
    private $cache;
    /** @var KernelInterface $kernel */
    private $kernel;

    /**
     * Constructeur de ChangelogController.
     * (On récupère un client http, un cache, ainsi que le kernel.)
     *
     * @param HttpClientInterface $httpClient
     * @param AdapterInterface    $cache
     * @param KernelInterface     $kernel
     */
    public function __construct(HttpClientInterface $httpClient, AdapterInterface $cache, KernelInterface $kernel)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->kernel = $kernel;
    }

    /**
     * @Route(
     *     path="/ajax/changelog/{page?1}",
     *     name="ajax-changelog",
     *     requirements={ "page": "\d+" }
     * )
     * @param int $page
     *
     * @return JsonResponse
     */
    public function getChangelog(int $page) : JsonResponse
    {
        // On récupère la version du build actuelle
        $actualBuildVersion = $this->kernel->getContainer()->getParameter('build_version');

        // On récupère les informations du dépôt gitlab
        $gitlabId = $this->getParameter('changelog_gitlab_id');
        $gitlabKey = $this->getParameter('changelog_gitlab_key');

        // On récupère la date de la dernière requête vers gitlab
        $cacheLastQuery = $this->cache->getItem('changelog.last_query');
        $cacheReleases = $this->cache->getItem('changelog.releases');
        $lastQuery = $cacheLastQuery->get();

        // Si la date de la dernière query vers gitlab date de plus de 2h
        if ($lastQuery < (new \DateTime())->sub(new \DateInterval('PT2H'))) {
            // Alors on va chercher les données sur gitlab
            $reponse = $this->httpClient->request(
                'GET',
                'https://forge.dgfip.finances.rie.gouv.fr/api/v4/projects/' . $gitlabId . '/releases',
                [ 'auth_bearer' => $gitlabKey ]
            );

            // On traite les données de gitlab
            $releases = [];
            $donnees = json_decode($reponse->getContent(), true);
            $affichable = false;
            foreach ($donnees as $release) {
                if (!$release['upcoming_release']) {
                    // Afin de pouvoir préparer les changelog sur Gitlab, nous n'affichons que les versions qui précèdent la version actuellement en ligne.
                    // (ou toutes si nous sommes en version "dev")
                    if ($affichable || $actualBuildVersion === 'dev' || $release['name'] === $actualBuildVersion) {
                        $affichable = true;
                        $releases[] = [
                            'name'         => $release['name'],
                            'type'         => str_starts_with($release['name'], 'v') ? self::VERSION_PROD : self::VERSION_PREPROD,
                            'description'  => Slimdown::render($release['description']),
                            'disponibleLe' => (new \DateTime($release['released_at']))
                                ->setTimezone(new \DateTimeZone('Europe/Paris'))->format('c')
                        ];
                    }
                }
            }

            // On met à jour la liste des releases, ainsi que la date de query dans le cache
            $cacheReleases->set($releases);
            $lastQuery = new \DateTime();
            $cacheLastQuery->set($lastQuery);
            $this->cache->save($cacheReleases);
            $this->cache->save($cacheLastQuery);
        } else {
            $releases = $cacheReleases->get();
        }

        // Si le mode débug de symfony n'est pas actif, alors on doit renvoyer uniquement les versions de type "prod"
        if (!$this->kernel->isDebug()) {
            $releases = array_filter($releases, function ($donnees) {
                return $donnees['type'] === "prod";
            });
        }

        // On calcul la pagination, ainsi que le lot de release à afficher
        $offset = self::VERSION_PAR_PAGE * ($page - 1);
        $totalPage = intval(ceil(count($releases) / self::VERSION_PAR_PAGE));
        $releasesToBeDisplayed = array_slice($releases, $offset, self::VERSION_PAR_PAGE);

        // On formate notre réponse
        return new JsonResponse([
            'info' => [
                'majLe' => $lastQuery->setTimezone(new \DateTimeZone('Europe/Paris'))->format('c'),
                'pagination' => [
                    'courante' => $page,
                    'total' => $totalPage,
                ]
            ],
            'releases' => $releasesToBeDisplayed
        ]);
    }
}
