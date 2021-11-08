<?php

namespace App\Tests\Calendrier;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Entity\References\StatutMep;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Faker;

class MepssiTest extends UserWebTestCase
{

    /** @var KernelBrowser $testClient */
    private $testClient;

    /**
     * Génère une MepSsi
     */
    public function creerMepSsi(): MepSsi
    {
        // On récupère notre Entity Manager dans notre contexte principal
        $em = static::getEm();

        // On initialise faker
        $faker = Faker\Factory::create('fr_FR');

        // Récupère des objets existants pour générer une Mep Ssi
        $composants = $em->getRepository(Composant::class)->findAll();
        $services = $em->getRepository(Service::class)->getPilotageEquipes();
        $demandeInterventions = $em->getRepository(DemandeIntervention::class)->findAll();
        $statusMeps = $em->getRepository(StatutMep::class)->findAll();
        $visibilite = ['DME', 'SI2A', 'SSI'];

        // On génère la mepssi
        $mepSsi = new MepSsi();
        $mepSsi->setDemandePar($services[rand(0, count($services) - 1)]);
        $mepSsi->setPalier('Palier');
        for ($i = 0; $i < rand(1, 3); $i++) {
            $composant = array_splice($composants, rand(0, count($composants) - 1), 1)[0];
            $mepSsi->addComposant($composant);
        }
        $mepSsi->setvisibilite($visibilite[rand(0, count($visibilite) - 1)]);
        $mepSsi->setEquipe($services[rand(0, count($services) - 1)]);
        for ($i = 0; $i < rand(0, 3); $i++) {
            $demandeIntervention = array_splice($demandeInterventions, rand(0, count($demandeInterventions) - 1), 1)[0];
            $mepSsi->addDemandesIntervention($demandeIntervention);
        }
        $mepSsi->setMes(new \DateTime('now'));
        $mepSsi->setStatut($statusMeps[rand(0, count($statusMeps) - 1)]);

        // Encore quelques propriétés non-obligatoires
        $mepSsi->setDescription($faker->text(256));
        $mepSsi->setImpacts('Quelques impacts');
        $mepSsi->setRisques('Quelques risques');
        $motClefs = [];
        for ($iMotClefs = 0; $iMotClefs <= rand(0, 5); $iMotClefs++) {
            $motClefs[] = $faker->text(16);
        }
        $mepSsi->setMotsClefs(count($motClefs) > 0 ? implode('; ', $motClefs): null);

        // On persiste
        $em->persist($mepSsi);
        $em->flush();

        return $mepSsi;
    }

    /**
     * Teste l'acces à la consultation d'une Mep Ssi
     * @dataProvider getRoles
     */
    public function testConsulterMepssi(string $roles)
    {
        // On crée notre client
        if ($roles === "NON_CONNECTE") {
            $this->testClient = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $this->testClient = static::createClientLoggedAs($service);
        }

        // On récupère l'Entity Manager
        self::$entityManager = static::getEm($this->testClient);

        // On récupère le kernel du client
        global $kernel;
        $kernel = $this->testClient->getKernel();

        // On génère la mep que l'on va tenter de consulter
        $mepSsi = $this->creerMepSsi();

        // On consulte la mep
        $mepSsiId = $mepSsi->getId();
        $this->testClient->request(Request::METHOD_GET, "/calendrier/mep-ssi/{$mepSsiId}");

        // On teste si on récupère bien la page
        $statusCodeAttendu = 'NON_CONNECTE' === $roles ? 302 : 200;
        $this->assertEquals($statusCodeAttendu, $this->testClient->getResponse()->getStatusCode());
        if ($statusCodeAttendu === 200) {
            $baseTitle = 'Détail de la MEP n°' . $mepSsiId;
            $this->assertPageTitleContains($baseTitle . ' | Gesip');
            $this->assertSelectorTextContains('.page-header h2', $baseTitle);
        }
    }

    /**
     * Teste l'accès et le fonctionnnement de la création d'une Mep Ssi
     * @dataProvider getRoles
     */
    public function testCreerMepssi(string $roles)
    {
        // On crée notre client
        if ($roles === "NON_CONNECTE") {
            $this->testClient = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $this->testClient = static::createClientLoggedAs($service);
        }

        // On récupère l'Entity Manager
        $em = static::getEm($this->testClient);
        self::$entityManager = $em;

        // On récupère le kernel du client
        global $kernel;
        $kernel = $this->testClient->getKernel();

        // On tente de créer une mep via la route dédiée
        $faker = Faker\Factory::create('fr_FR');
        $mepSsiCount = count($em->getRepository(MepSsi::class)->findAll());
        $composants = $em->getRepository(Composant::class)->findAll();
        $services = $em->getRepository(Service::class)->getPilotageEquipes();
        $statusMeps = $em->getRepository(StatutMep::class)->findAll();
        $composantIds = [];
        for ($i = 0; $i < rand(1, 5); $i++) {
            $composant = array_splice($composants, rand(0, count($composants) - 1), 1)[0];
            $composantIds[] = $composant->getId();
        }
        $visibilites = ['DME', 'SI2A', 'SSI'];
        $this->testClient->request(Request::METHOD_POST, '/calendrier/mep-ssi/creer', [
            'mep_ssi' => [
                'palier'       => $faker->text(64),
                'composants'   => $composantIds,
                'visibilite'   => $visibilites[rand(0, count($visibilites) - 1)],
                'equipe'       => ($services[rand(0, count($services) - 1)])->getId(),
                'mes'          => date('d/m/Y', time() + rand(86400, 8640000)),
                'statut'       => ($statusMeps[rand(0, count($statusMeps) - 1)])->getId(),
                '_token'       => $this->testClient->getContainer()->get('security.csrf.token_manager')->getToken('mep_ssi')->getValue(),
            ]
        ]);

        // On compare ce qu'on obtient à ce qu'on attendait
        $statusCodeAttendu = in_array($roles, ['ROLE_ADMIN', 'ROLE_DME', 'NON_CONNECTE']) ? 302 : 403;
        $this->assertEquals($statusCodeAttendu, $this->testClient->getResponse()->getStatusCode());
        if ($statusCodeAttendu === 302 && 'NON_CONNECTE' !== $roles) {
            // On teste aussi, si on est bien redirigé vers la liste
            $this->assertEquals('/calendrier/mep-ssi/liste', $this->testClient->getResponse()->headers->get('location'));
            // On teste si il existe maintenant plus de mepssi
            $this->assertEquals($mepSsiCount + 1, count($em->getRepository(MepSsi::class)->findAll()));
        }
    }

    /**
     * Teste l'accès et le fonctionnnement de la modification d'une Mep Ssi
     * @dataProvider getRoles
     */
    public function testModifierMepssi(string $roles)
    {
        // On crée notre client
        if ($roles === "NON_CONNECTE") {
            $this->testClient = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $this->testClient = static::createClientLoggedAs($service);
        }

        // On récupère l'Entity Manager
        $em = static::getEm($this->testClient);
        self::$entityManager = $em;

        // On récupère le kernel du client
        global $kernel;
        $kernel = $this->testClient->getKernel();

        // On génère la mep que l'on va tenter de modifier
        $mepSsi = $this->creerMepSsi();
        $mepSsiCount = count($em->getRepository(MepSsi::class)->findAll());

        // On modifie certains champs
        $faker = Faker\Factory::create('fr_FR');
        $composantIds = [];
        foreach ($mepSsi->getComposants() as $composant) {
            $composantIds[] = $composant->getId();
        }
        $newPalier = hash('sha256', microtime(true));
        $this->testClient->request(Request::METHOD_POST, '/calendrier/mep-ssi/modifier/' . $mepSsi->getId(), [
            'mep_ssi' => [
                'palier'       => $newPalier,
                'composants'   => $composantIds,
                'visibilite'   => $mepSsi->getVisibilite(),
                'equipe'       => $mepSsi->getEquipe()->getId(),
                'mes'          => $mepSsi->getMes()->format('d/m/Y'),
                'statut'       => $mepSsi->getStatut()->getId(),
                '_token'       => $this->testClient->getContainer()->get('security.csrf.token_manager')->getToken('mep_ssi')->getValue(),
            ]
        ]);

        // On teste si on accède bien au service
        $statusCodeAttendu = in_array($roles, ['ROLE_ADMIN', 'ROLE_DME', 'NON_CONNECTE']) ? 302 : 403;
        $this->assertEquals($statusCodeAttendu, $this->testClient->getResponse()->getStatusCode());
        if ($statusCodeAttendu === 302 && 'NON_CONNECTE' !== $roles) {
            // On teste aussi, si on est bien redirigé vers la liste
            $this->assertEquals('/calendrier/mep-ssi/liste', $this->testClient->getResponse()->headers->get('location'));
            // On teste si il existe maintenant autant de mepssi qu'avant
            $this->assertEquals($mepSsiCount, count($em->getRepository(MepSsi::class)->findAll()));
            // On tente de récupérer notre Mep Ssi et on regarde si son palier a bien changé
            $mepSsi2 = $em->getRepository(MepSsi::class)->find($mepSsi->getId());
            $this->assertEquals($newPalier, $mepSsi2->getPalier());
        }
    }

    /**
     * Teste l'accès et le fonctionnnement de l'archivage d'une Mep Ssi
     * @dataProvider getRoles
     */
    public function testArchiverMepssi(string $roles)
    {
        // On crée notre client
        if ($roles === "NON_CONNECTE") {
            $this->testClient = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $this->testClient = static::createClientLoggedAs($service);
        }

        // On récupère l'Entity Manager
        self::$entityManager = static::getEm($this->testClient);

        // On récupère le kernel du client
        global $kernel;
        $kernel = $this->testClient->getKernel();

        // On génère la mep que l'on va tenter d'archiver
        $mepSsi = $this->creerMepSsi();

        // On tente d'archiver la mep
        $mepSsiId = $mepSsi->getId();
        $this->testClient->request(Request::METHOD_GET, "/calendrier/mep-ssi/archiver/{$mepSsiId}");

        // On teste si on accède bien au service
        $statusCodeAttendu = in_array($roles, ['ROLE_ADMIN', 'ROLE_DME', 'NON_CONNECTE']) ? 302 : 403;
        $this->assertEquals($statusCodeAttendu, $this->testClient->getResponse()->getStatusCode());
        if ($statusCodeAttendu === 302 && 'NON_CONNECTE' !== $roles) {
            // On teste si la Mep Ssi a bien été archivée
            $this->assertEquals('ARCHIVE', $mepSsi->getStatut()->getLabel());
            // On teste aussi, si on est bien redirigé vers la liste
            $this->assertEquals('/calendrier/mep-ssi/liste', $this->testClient->getResponse()->headers->get('location'));
        }
    }

    /**
     * Fourni la liste des rôles et des code status attendus.
     */
    public function getRoles(): array
    {
        return [
            'NON_CONNECTE' => [
                'NON_CONNECTE',
            ],
            Service::ROLE_ADMIN => [
                Service::ROLE_ADMIN
            ],
            Service::ROLE_DME => [
                Service::ROLE_DME
            ],
            Service::ROLE_INTERVENANT => [
                Service::ROLE_INTERVENANT
            ],
            Service::ROLE_INVITE => [
                Service::ROLE_INVITE,
            ]
        ];
    }
}
