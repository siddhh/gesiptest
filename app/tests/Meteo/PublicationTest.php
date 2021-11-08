<?php

namespace App\Tests\Meteo;

use App\Entity\Composant;
use App\Entity\Meteo\Evenement;
use App\Entity\Meteo\Publication;
use App\Entity\References\ImpactMeteo;
use App\Entity\References\MotifIntervention;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class PublicationTest extends UserWebTestCase
{
    /**
     * ------ Fonctions privées ------
     */
    /**
     * On crée un service admin
     *
     * @return Service
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createServiceAdmin(): Service
    {
        $service = new Service();
        $service->setLabel("Service ADMIN " . uniqid());
        $service->setRoles([ Service::ROLE_ADMIN ]);
        $service->setEmail('service' . uniqid() . '@local');
        $service->setMotdepasse('toto');
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * On crée un composant
     *
     * @return Composant
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createComposant(): Composant
    {
        // On crée les objets annexe
        $usager = (new Usager())->setLabel('Usager Perimetre Applicatif');
        static::getEm()->persist($usager);
        $typeElement = (new TypeElement())->setLabel('Type element Perimetre Applicatif');
        static::getEm()->persist($typeElement);

        // On crée le composant
        $composant = new Composant();
        $composant->setLabel('Composant ' . uniqid());
        $composant->setUsager($usager);
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur Perimetre Applicatif");
        $composant->setMeteoActive(true);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * On crée un évènement
     *
     * @return Evenement
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createEvenement(Composant $composant): Evenement
    {
        // On crée les objets annexe
        $impactMeteo = (new ImpactMeteo())->setLabel('Impact météo');
        static::getEm()->persist($impactMeteo);
        $motifIntervention = (new MotifIntervention())->setLabel('Motif intervention');
        static::getEm()->persist($motifIntervention);

        // On crée l'évènement
        $evenement = new Evenement();
        $evenement->setComposant($composant);
        $evenement->setImpact($impactMeteo);
        $evenement->setTypeOperation($motifIntervention);
        $evenement->setDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 12:00:00', new \DateTimeZone('Europe/Paris'))->setTimezone(new \DateTimeZone('UTC')));
        $evenement->setFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-10 14:00:00', new \DateTimeZone('Europe/Paris'))->setTimezone(new \DateTimeZone('UTC')));
        static::getEm()->persist($evenement);

        // On tire la chasse
        static::getEm()->flush();
        return $evenement;
    }

    /**
     * ------ Fonctions de tests ------
     */
    /**
     * Teste d'accès à la partie publication de la météo
     * @dataProvider getAccesIndexRoles
     */
    public function testAccesIndexParRole(string $role, int $statusCode)
    {
        if ($role === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($role);
            $client = static::createClientLoggedAs($service);
        }

        $client->request(Request::METHOD_GET, '/meteo/publication');

        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Publication / Dépublication des tableaux de bord Météo');
            $this->assertSelectorTextContains('.page-header h2', 'Publication / Dépublication des tableaux de bord Météo');
        }
    }
    public function getAccesIndexRoles(): array
    {
        return [
            'NON_CONNECTE' => [
                'NON_CONNECTE',
                302
            ],
            Service::ROLE_ADMIN => [
                Service::ROLE_ADMIN,
                200
            ],
            Service::ROLE_DME => [
                Service::ROLE_DME,
                403
            ],
            Service::ROLE_INTERVENANT => [
                Service::ROLE_INTERVENANT,
                403
            ],
            Service::ROLE_INVITE => [
                Service::ROLE_INVITE,
                403
            ]
        ];
    }

    /**
     * Teste la publication météo d'une période
     */
    public function testPublicationMeteo()
    {
        // On crée le client et on récupère l'entity manager
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $service = $this->createServiceAdmin();

        // On crée un composant
        $composant = $this->createComposant();

        // On crée un évènement pour le composant
        $this->createEvenement($composant);

        // On prépare notre requête de publication
        $donneesRequete = [
            'action' => 'publication',
            'debut' => '2021-01-07',
            'fin' => '2021-01-13',
        ];

        // On se connecte
        static::loginAs($client, $service);

        // On effectue la requête et on contrôle le résultat
        $client->request(Request::METHOD_POST, '/ajax/meteo/periode/action', $donneesRequete);

        // On récupère la dernière entrée dans les publications et on vérifie les informations enregistrée
        $publicationMeteo = self::getEmRepository(Publication::class, $client)->findOneBy([
            'periodeDebut' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            'periodeFin' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
        ]);
        $this->assertNotNull($publicationMeteo);

        $composantMeteo = self::getEmRepository(\App\Entity\Meteo\Composant::class, $client)->findOneBy([
            'composant' => $composant,
            'periodeDebut' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            'periodeFin' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
        ]);
        $this->assertNotNull($composantMeteo);
    }

    /**
     * Teste la dépublication météo d'une période
     */
    public function testDepublicationMeteo()
    {
        // On crée le client et on récupère l'entity manager
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $service = $this->createServiceAdmin();

        // On crée un composant
        $composant = $this->createComposant();

        // On crée un évènement pour le composant
        $this->createEvenement($composant);

        // On crée les données de publications
        $publicationMeteo = new Publication();
        $publicationMeteo->setPeriodeDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'));
        $publicationMeteo->setPeriodeFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'));
        $em->persist($publicationMeteo);

        $composantMeteo = new \App\Entity\Meteo\Composant();
        $composantMeteo->setComposant($composant);
        $composantMeteo->setPeriodeDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'));
        $composantMeteo->setPeriodeFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'));
        $composantMeteo->setMeteo(\App\Entity\Meteo\Composant::ENSOLEILLE);
        $composantMeteo->setDisponibilite(100);
        $em->persist($composantMeteo);
        $em->flush();

        // On vérifie que la publication est bien dans la base
        $publicationMeteo = self::getEmRepository(Publication::class, $client)->findOneBy([
            'periodeDebut' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            'periodeFin' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
        ]);
        $this->assertNotNull($publicationMeteo);

        $composantMeteo = self::getEmRepository(\App\Entity\Meteo\Composant::class, $client)->findOneBy([
            'composant' => $composant,
            'periodeDebut' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            'periodeFin' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
        ]);
        $this->assertNotNull($composantMeteo);

        // On prépare notre requête de publication
        $dataRequest = [
            'action' => 'depublication',
            'debut' => '2021-01-07',
            'fin' => '2021-01-13',
        ];

        // On se connecte
        static::loginAs($client, $service);

        // On effectue la requête et on contrôle le résultat
        $client->request(Request::METHOD_POST, '/ajax/meteo/periode/action', $dataRequest);

        // On récupère la dernière entrée dans les publications et on vérifie les informations enregistrée
        $publicationMeteo = self::getEmRepository(Publication::class, $client)->findOneBy([
            'periodeDebut' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            'periodeFin' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
        ]);
        $this->assertNull($publicationMeteo);

        $composantMeteo = self::getEmRepository(\App\Entity\Meteo\Composant::class, $client)->findOneBy([
            'composant' => $composant,
            'periodeDebut' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            'periodeFin' => \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
        ]);
        $this->assertNull($composantMeteo);
    }
}
