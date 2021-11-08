<?php

namespace App\Tests\Gestion;

use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ServicesTest extends UserWebTestCase
{

    private const RECORDS_BY_PAGE = 20;

    /**
     * Teste d'accès à la partie gestion des services
     * @dataProvider getAccesParRoles
     */
    public function testGestionServicesControleDesAcces(string $role, int $statusCode)
    {
        $client = static::getClientByRole($role);
        $client->request(Request::METHOD_GET, '/gestion/services');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Gestion des services');
            $this->assertSelectorTextContains('.page-header h2', 'Liste des services');
        }
    }

    /**
     * Teste si on peut creer un nouveau service après s'être connecté en administrateur
     */
    public function testCreerService()
    {
        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        // on tente de récupérer la page de creation service
        $crawler = $client->request('GET', "/gestion/services/creation");
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Ajout d\'un service');
        // on tente de valider le formulaire
        $form = $crawler->selectButton('Enregistrer le service')->form();
        $form->setValues([
            'service[label]'                => 'toto',
            'service[estServiceExploitant]' => 1,
            'service[structurePrincipale]'  => 1,
            'service[roles]'                => Service::ROLE_ADMIN,
            'service[email]'                => 'cool@dgfip.finances.gouv.fr',
        ]);
        $crawler = $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertPageTitleContains('Gestion des services');
        $serviceRepository = self::getEm()->getRepository(Service::class);
        $dernierService = $serviceRepository->findOneBy([], ['id' => 'DESC']);
        $this->assertEquals($dernierService->getLabel(), 'toto');
        $this->assertEquals($dernierService->getEmail(), 'cool@dgfip.finances.gouv.fr');
    }

    /**
     * Teste si on peut modifier un service après s'être connecté en administrateur
     */
    public function testModificationService()
    {
        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        // on tente de récupérer la page de modification de service
        $crawler = $client->request('GET', "/gestion/services/2/modifier");
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Modification du service');
        // on tente de valider le formulaire
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form->setValues([
            'service[label]' => 'titi',
            'service[estServiceExploitant]' => 1,
            'service[structurePrincipale]' => 1,
            'service[roles]' => 'ROLE_ADMIN',
            'service[email]' => 'coolbe@dgfip.finances.gouv.fr',
        ]);
        $crawler = $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertPageTitleContains('Gestion des services');
        $serviceRepository = self::getEm($client)->getRepository(Service::class);
        $modifService = $serviceRepository->find(2);
        $this->assertEquals($modifService->getLabel(), 'titi');
        $this->assertEquals($modifService->getEmail(), 'coolbe@dgfip.finances.gouv.fr');
    }


    /**
     * Teste si on obtient bien le bon résultat au niveau de l'index des services
     */
    public function testServiceIndex()
    {
        // Recherche des services sur la base
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        $services = self::getEm()->getRepository(Service::class)->findBy(['supprimeLe' => null], ['label' => 'ASC']);
        $service = reset($services);
        $client->request('GET', "/ajax/services/listing/1");
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        // traduction réponse json en php
        $data = json_decode($response->getContent(), true);
        // Test de comparaison
        $raccourcie = $data['donnees'][0];
        $this->assertEquals($raccourcie['id'], $service->getId());
        $this->assertEquals($raccourcie['label'], $service->getLabel());
        $this->assertEquals($raccourcie['email'], $service->getEmail());
        $this->assertEquals(count($data['donnees']), count($services) > self::RECORDS_BY_PAGE ? self::RECORDS_BY_PAGE : count($services));   // vérifie qu'aucune autre info est fournie (genre le mot de passe!)
    }

    /**
     * Teste si on obtient bien le bon résultat au niveau de l'index d'un service
     */
    public function testServiceIndexUn()
    {
        // Recherche d'un service sur la base
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        $services = self::getEm()
        ->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->where('s.label LIKE :search')
            ->setParameter('search', '%DME%')
            ->getQuery()
            ->getResult();
        $service = reset($services);
        $client->request('GET', "/ajax/services/listing/1?filtre=DME");
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        // traduction réponse json en php
        $data = json_decode($response->getContent(), true);
        // Test de comparaison
        $raccourcie = $data['donnees'][0];
        $this->assertEquals($raccourcie['id'], $service->getId());
        $this->assertEquals($raccourcie['label'], $service->getLabel());
        $this->assertEquals($raccourcie['email'], $service->getEmail());
        $this->assertEquals(count($data['donnees']), count($services) > self::RECORDS_BY_PAGE ? self::RECORDS_BY_PAGE : count($services));   // vérifie qu'aucune autre info est fournie (genre le mot de passe!)
    }

    public function getAccesParRoles(): array
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
                200
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
}
