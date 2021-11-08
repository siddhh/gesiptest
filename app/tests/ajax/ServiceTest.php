<?php

namespace App\Tests\Ajax;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Service;

class ServiceTest extends WebTestCase
{

    /**
     * Teste si le process de réinitialisation de mot de passe fonctionne correctement
     */
    public function testServiceReinitialisationMotdepasse()
    {
        // Appel du webservice de réinitialisation de mot de passe
        $client = static::createClient();
        $serviceId = 5;
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $service = $em->getRepository(Service::class)->find($serviceId);
        $client->request('POST', "/ajax/service/motdepasse/reinitialise/{$serviceId}");
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($data['statut'], 1);
        $this->assertEquals($data['serviceId'], $serviceId);
        $this->assertEquals($data['serviceLabel'], $service->getLabel());
        // Vérifie la validité du mail de réinitialisation
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $service->getEmail());
        $this->assertEmailTextBodyContains($email, "Service : {$service->getLabel()}");
        $this->assertEmailTextBodyContains($email, 'Mot de passe : ');
        $this->assertEmailHtmlBodyContains($email, "Service : {$service->getLabel()}");
        $this->assertEmailHtmlBodyContains($email, 'Mot de passe : ');
    }

    /**
     * Teste si le webservice get fonctionne correctement
     */
    public function testServiceGet()
    {
        // Demande les infos d'un service
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $service = $em->getRepository(Service::class)->findOneBy([]);
        $client->request('GET', "/ajax/service/{$service->getId()}");
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($data['id'], $service->getId());
        $this->assertEquals($data['label'], $service->getLabel());
        $this->assertEquals($data['email'], $service->getEmail());
        $this->assertEquals(count($data), 3);   // vérifie qu'aucune autre info est fournie (genre le mot de passe!)
    }
}
