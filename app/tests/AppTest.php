<?php

namespace App\Tests;

class AppTest extends UserWebTestCase
{
    /**
     * En mode non connecté, la homepage doit renvoyer une redirection 302 vers la page de connexion.
     */
    public function testAffichageAccueilNonConnecte()
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isRedirect('/connexion'));
    }

    /**
     * En mode connecté, on doit afficher le layout de base avec le nom du service connecté en haut à droite de l'écran.
     */
    public function testAffichageAccueilConnecte()
    {
        global $kernel;
        $service = static::getOneService(['label' => '0 Service Administrateur']);
        $client = static::createClientLoggedAs($service);
        $kernel = $client->getKernel();
        $this->loginAs($client, $service);
        $client->request('GET', '/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertPageTitleContains('Accueil | Gesip');
        $this->assertSelectorTextContains('header .container', $service->getLabel());
    }
}
