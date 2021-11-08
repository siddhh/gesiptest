<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Service;

class ConnexionTest extends WebTestCase
{

    /**
     * Teste lorsqu'on tente de se connecter avec un mauvais mot de passe:
     *  - Si il est possible de valider le formulaire
     *  - Si nous sommes bien redirigé vers le formulaire de connexion
     *  - Si le message "mot de passe incorrect..." s'affiche.
     */
    public function testConnexionEchouee()
    {
        // tente de récupérer la page de connexion
        $client = static::createClient();
        $crawler = $client->request('GET', "/connexion");
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Connexion');
        // tente de valider le formulaire
        $form = $crawler->selectButton('Connexion')->form();
        $form['serviceId'] = 10;
        $form['password'] = 'BadPassword12345';
        $crawler = $client->submit($form);
        // vérifie que le message affiche que c'est raté
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Connexion');
        $failedText = 'Votre mot de passe est incorrect. Votre identification a échoué.';
        $this->assertSelectorTextContains('main', $failedText);
    }

    /**
     * Teste si l'authentification d'un service fonctionne correctement:
     *  - Si il est possible de valider le formulaire
     *  - Si nous sommes bien redirigé vers l'accueil.
     *  - Si on trouve le nom du service connecté en haut de page
     *  - Si on parvient à se déconnecter via le lien "déconnexion"
     */
    public function testConnexionDeconnexion()
    {
        global $kernel;
        // Récupère un service au hasard en base de données
        $client = static::createClient();
        $kernel = $client->getKernel();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $service = $em->getRepository(Service::class)->find(rand(1, 16));
        // tente de récupérer la page de connexion
        $crawler = $client->request('GET', "/connexion");
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Connexion');
        // tente de valider le formulaire
        $form = $crawler->selectButton('Connexion')->form();
        $form['serviceId'] = $service->getId();
        $form['password'] = 'azerty';
        $crawler = $client->submit($form);
        // vérifie que c'est réussie (connexion puis redirection vers l'accueil)
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Accueil');
        $this->assertSelectorTextContains('header', $service->getLabel());
        // vérifie la déconnexion en cliquant sur le lien
        $lienDeconnexion = $crawler->selectLink('déconnexion')->link();
        $client->click($lienDeconnexion);
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Connexion');
    }
}
