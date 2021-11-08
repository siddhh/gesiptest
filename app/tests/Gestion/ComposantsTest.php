<?php

namespace App\Tests\Gestion;

use App\Entity\Composant;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ComposantsTest extends UserWebTestCase
{
    private const RECORDS_BY_PAGE = 20;

    /**
     * Teste d'accès à la partie gestion des composants
     * @dataProvider getAccesParRoles
     */
    public function testGestionComposantsControleDesAcces(string $roles, int $statusCode)
    {
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }
        $client->request(Request::METHOD_GET, '/gestion/composants/creation');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Création d\'un nouveau composant');
        }
    }

    // Teste si on peut créer un nouveau composant après s'être connecté en administrateur
    public function testCreerComposant()
    {
        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);

        //Recherche du dernier service exploitant
        $servicesExploitants = self::getEm($client)
            ->getRepository(Service::class)
            ->findBy(
                [
                    'label' => 'DESC',
                    'estServiceExploitant' => true,
                ]
            );
        $dernierServiceExploitant = reset($servicesExploitants);

        //Recherche du dernier service équipe
        $servicesEquipes = self::getEm($client)
            ->getRepository(Service::class)
            ->findBy(
                [
                    'label' => 'DESC',
                    'estPilotageDme' => true,
                ]
            );
        $dernierServiceEquipe = reset($servicesEquipes);

        //Recherche du dernier service bureau rattachement
        $servicesBureauxRattachements = self::getEm($client)
            ->getRepository(Service::class)
            ->findBy(
                [
                    'label' => 'DESC',
                    'estBureauRattachement' => true,
                ]
            );
        $dernierServiceBureauRattachement = reset($servicesBureauxRattachements);

        // on tente de récupérer la page de création composant
        $crawler = $client->request('GET', "/gestion/composants/creation");
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Création d\'un nouveau composant');
        // on tente de valider le formulaire
        $form = $crawler->selectButton('Suivant')->form();
        $form->setValues([
            'composant[label]'                => 'Sunshine1987',
            'composant[codeCarto]'            => 'BipBip123',
            'composant[usager]'               => 8,
            'composant[domaine]'              => 23,
            'composant[exploitant]'           => $dernierServiceExploitant,
            'composant[meteoActive]'          => 1,
            'composant[equipe]'               => $dernierServiceEquipe,
            'composant[pilote]'               => 1,
            'composant[piloteSuppleant]'      => 1,
            'composant[typeElement]'          => 1,
            'composant[estSiteHebergement]'   => 1,
            'composant[bureauRattachement]'   => $dernierServiceBureauRattachement,


        ]);
        $crawler = $client->submit($form);
        $crawler = $client->followRedirect();
        $this->assertPageTitleContains('Recherche des composants');
        $composantRepository = self::getEm($client)->getRepository(Composant::class);
        $dernierComposant = $composantRepository->findOneBy([], ['id' => 'DESC']);
        $this->assertEquals($dernierComposant->getLabel(), 'Sunshine1987');
    }


    // Test si on peut modifier un composant après s'être connecté en administrateur
    public function testModificationComposant()
    {
        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);

        //Recherche du dernier service exploitant
        $servicesExploitants = self::getEm($client)
            ->getRepository(Service::class)
            ->findBy(
                [
                    'label' => 'DESC',
                    'estServiceExploitant' => true,
                ]
            );
        $dernierServiceExploitant = reset($servicesExploitants);

        //Recherche du dernier service equipe
        $servicesEquipes = self::getEm($client)
            ->getRepository(Service::class)
            ->findBy(
                [
                    'label' => 'DESC',
                    'estPilotageDme' => true,
                ]
            );
        $dernierServiceEquipe = reset($servicesEquipes);

        //Recherche du dernier service bureau rattachement
        $servicesBureauxRattachements = self::getEm($client)
            ->getRepository(Service::class)
            ->findBy(
                [
                    'label' => 'DESC',
                    'estBureauRattachement' => true,
                ]
            );
        $dernierServiceBureauRattachement = reset($servicesBureauxRattachements);

        // on tente de récupérer la page de modification de service
        $crawler = $client->request('GET', "/gestion/composants/2/modifier");
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Modification d\'un composant');

        // on tente de valider le formulaire
        $form = $crawler->selectButton('Suivant')->form();
        $form->setValues([
            'composant[label]'                => 'I_Am_Happy_13',
            'composant[codeCarto]'            => 'V.119',
            'composant[usager]'               => 9,
            'composant[domaine]'              => 24,
            'composant[exploitant]'           => $dernierServiceExploitant,
            'composant[meteoActive]'          => 2,
            'composant[equipe]'               => $dernierServiceEquipe,
            'composant[pilote]'               => 5,
            'composant[piloteSuppleant]'      => 6,
            'composant[typeElement]'          => 2,
            'composant[estSiteHebergement]'   => 1,
            'composant[bureauRattachement]'   => $dernierServiceBureauRattachement,

        ]);
        $crawler = $client->submit($form);
        $crawler = $client->followRedirect();
        $composantRepository = self::getEm($client)->getRepository(Composant::class);
        $this->assertPageTitleContains('Recherche des composants');
        $modifComposant = $composantRepository->find(2);
        $this->assertEquals($modifComposant->getLabel(), 'I_Am_Happy_13');
    }

    /**
     * Teste si on obtient bien le bon résultat au niveau de l'index des composants
     */
    public function testComposantIndex()
    {
        // Recherche des composants sur la base
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        $composants = self::getEm($client)
            ->getRepository(Composant::class)
            ->findBy(
                ['archiveLe' => null],
                ['label' => 'ASC']
            );
        $composant = reset($composants);

        $client->request('POST', "/ajax/composant/recherche/");

        // Vérifie le status de la requête
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));

        // traduction réponse json en php
        $data = json_decode($response->getContent(), true);

        // Test de comparaison
        $premierComposant = $data['donnees'][0];
        $this->assertEquals($premierComposant['id'], $composant->getId());
        $this->assertEquals($premierComposant['label'], $composant->getLabel());
        $this->assertEquals(count($data['donnees']), count($composants) > self::RECORDS_BY_PAGE ? self::RECORDS_BY_PAGE : count($composants));   // vérifie qu'aucune autre info est fournie (genre le mot de passe!)
    }

    /**
     * Teste si on obtient bien le bon résultat au niveau de l'index des composants avec 1 critère
     * dans le champ composant
     */
    public function testComposantIndividuelIndex()
    {
        // Recherche des composants sur la base
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        $composants = self::getEm($client)
            ->getRepository(Composant::class)
            ->findBy(
                ['archiveLe' => null],
                ['label' => 'ASC']
            );
            $composant = reset($composants);
            $clientParams = [
                'label' => $composant->getLabel(),
            ];
            $composant = reset($composants);

            $client->request('POST', "/ajax/composant/recherche/", $clientParams);

        // Vérifie le status de la requête
            $response = $client->getResponse();
            $this->assertResponseIsSuccessful();
            $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));

        // traduction réponse json en php
            $data = json_decode($response->getContent(), true);

        // Test de comparaison
            $this->assertEquals($data['donnees'][0]['id'], $composant->getId());
            $this->assertEquals($data['donnees'][0]['label'], $composant->getLabel());
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
