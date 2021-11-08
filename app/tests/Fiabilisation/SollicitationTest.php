<?php

namespace App\Tests\Fiabilisation;

use App\Entity\Service;
use App\Entity\Sollicitation;
use App\Tests\UserWebTestCase;
use Doctrine\ORM\EntityManager;

class SollicitationTest extends UserWebTestCase
{

    /**
     * ------ Fonctions privées ------
     */
    /**
     * On crée un service demandeur
     * @return Service
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createService(): Service
    {
        $service = new Service();
        $service->setLabel("Service " . uniqid());
        $service->setRoles([ Service::ROLE_INTERVENANT ]);
        $service->setEmail('service' . uniqid() . '@local');
        $service->setMotdepasse('toto');
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * On crée un service admin
     * @return Service
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createServiceAdmin(): Service
    {
        $service = new Service();
        $service->setLabel("Service Admin");
        $service->setRoles([ Service::ROLE_ADMIN ]);
        $service->setEmail('service-admin@local');
        $service->setMotdepasse('toto');
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * On crée un service DME
     * @return Service
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createServiceDME(): Service
    {
        $service = new Service();
        $service->setLabel("Service DME");
        $service->setRoles([ Service::ROLE_DME ]);
        $service->setEmail('service-dme@local');
        $service->setMotdepasse('toto');
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * ------ TESTS ------
     */
    /**
     * On test si la page est bien inaccessible pour un service non admin non dme
     */
    public function testAccesInterditALaPageDeRelance()
    {
        // On crée notre service
        $service = $this->createService();

        // On test le comportement
        $client = static::createClientLoggedAs($service);
        $client->request('GET', '/gestion/fiabilisation/sollicitation');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test si les administrateurs ont accès à cette page
     */
    public function testAccessAutoriseAdminALaPageDeRelance()
    {
        // On crée notre service administrateur
        $serviceAdmin = $this->createServiceAdmin();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceAdmin);
        $client->request('GET', '/gestion/fiabilisation/sollicitation');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * On test si les D%E ont accès à cette page
     */
    public function testAccessAutoriseDmeALaPageDeRelance()
    {
        // On crée notre service dme
        $serviceDme = $this->createServiceDME();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDme);
        $client->request('GET', '/gestion/fiabilisation/sollicitation');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }


    /**
     * On test que l'envoi de mail se fait bien quand on fait une relance
     */
    public function testRelanceDeServiceValide()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée nos services
        $service1 = $this->createService();
        $service2 = $this->createService();
        $serviceAdmin = $this->createServiceAdmin();

        // On prépare les paramètres
        $clientParams = [
            'servicesIds' => [
                $service1->getId(),
                $service2->getId()
            ],
            'copyMail' => false
        ];

        // On envoi la requête
        static::loginAs($client, $serviceAdmin);
        $client->request('POST', '/ajax/fiabilisation/sollicitation/relancer', $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On rafraichi les services
        $em->refresh($service1);
        $em->refresh($service2);

        // On test que la date de dernière sollicitation est ajoutée
        $aujourdhui = new \DateTime();
        $this->assertNotNull($service1->getDateDerniereSollicitation());
        $this->assertEquals($aujourdhui->format('d/m/Y'), $service1->getDateDerniereSollicitation()->format("d/m/Y"));
        $this->assertNotNull($service2->getDateDerniereSollicitation());
        $this->assertEquals($aujourdhui->format('d/m/Y'), $service2->getDateDerniereSollicitation()->format("d/m/Y"));

        // On test l'ajout dans la table sollicitation
        $sollicitationService1 = $em->getRepository(Sollicitation::class)->findBy([ 'serviceSollicite' => $service1, 'sollicitePar' => $serviceAdmin ]);
        $this->assertEquals(1, count($sollicitationService1));
        $sollicitationService2 = $em->getRepository(Sollicitation::class)->findBy([ 'serviceSollicite' => $service2, 'sollicitePar' => $serviceAdmin ]);
        $this->assertEquals(1, count($sollicitationService2));
    }
}
