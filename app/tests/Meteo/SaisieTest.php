<?php

namespace App\Tests\Meteo;

use App\Entity\Composant;
use App\Entity\Meteo\Evenement;
use App\Entity\References\ImpactMeteo;
use App\Entity\References\MotifIntervention;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class SaisieTest extends UserWebTestCase
{
    /**
     * ------ Fonctions privées ------
     */
    /**
     * On crée un service avec un rôle particulier
     *
     * @param string $role
     *
     * @return Service
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createService(string $role): Service
    {
        $service = new Service();
        $service->setLabel("Service $role " . uniqid());
        $service->setRoles([ $role ]);
        $service->setEmail('service' . uniqid() . '@local');
        $service->setMotdepasse('toto');
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * On crée un composant
     *
     * @param Service $serviceExploitant
     * @param array   $composantComportement
     *
     * @return Composant
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createComposant(Service $serviceExploitant, array $composantComportement): Composant
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
        if ($composantComportement['exploitant']) {
            $composant->setExploitant($serviceExploitant);
        }
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur Perimetre Applicatif");
        $composant->setMeteoActive($composantComportement['meteoActive']);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * ------ Fonctions de tests ------
     */
    /**
     * Teste d'accès à la partie saisie de la météo
     * @dataProvider getAccesIndexRoles
     */
    public function testAccesIndexParRole(string $role, int $statusCode)
    {
        // On crée notre client
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $service = null;
        // Si un role de service connecté est demandé
        if ($role !== "NON_CONNECTE") {
            // On va chercher notre service correspondant à un role défini
            $service = self::getOneServiceFromRole($role);
            // Dans nos tests d'accès, un ROLE_INTERVENANT est forcément exploitant !
            //  (ce qui n'est pas toujours le cas dans les fixtures)
            if ($role === Service::ROLE_INTERVENANT) {
                $service->setEstServiceExploitant(true);
            }

            // On se connecte
            self::loginAs($client, $service);
        }

        $client->request(Request::METHOD_GET, '/meteo/saisie');

        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Saisie des tableaux de bord SI des composants');
            $this->assertSelectorTextContains('.page-header h2', 'Saisie des tableaux de bord SI des composants');
        }
    }
    public function getAccesIndexRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ]
        ];
    }

    /**
     * Teste d'accès à la partie saisie de la météo en fonction d'un rôle et d'un composant
     *
     * @dataProvider getAccesSaisieRoles
     *
     * @param string $role
     * @param array  $composantComportement
     * @param int    $statusCode
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testAccesSaisieParRoleEtSituation(string $role, array $composantComportement, int $statusCode)
    {
        // On crée le client et on récupère l'entity manager
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $service = $this->createService($role);

        // On crée un composant
        $composant = $this->createComposant($service, $composantComportement);

        // On se connecte en tant que service, si nous devons nous connecter
        if ($role !== 'NON_CONNECTE') {
            static::loginAs($client, $service);
        }

        // On vérifie que l'on a accès à la page de saisie d'un du composant
        $client->request(Request::METHOD_GET, '/meteo/modifier/' . $composant->getId() . '/20210107');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
    }
    public function getAccesSaisieRoles(): array
    {
        return [
            'NON_CONNECTE' =>
                [ 'NON_CONNECTE', [ 'exploitant' => true, 'meteoActive' => true ], 302 ],

            'ROLE_ADMIN + Composant : Exploitant = Oui - meteoActive = Oui' =>
                [ Service::ROLE_ADMIN, [ 'exploitant' => true, 'meteoActive' => true ], 200 ],
            'ROLE_ADMIN + Composant : Exploitant = Oui - meteoActive = Non' =>
                [ Service::ROLE_ADMIN, [ 'exploitant' => true, 'meteoActive' => false ], 400 ],
            'ROLE_ADMIN + Composant : Exploitant = Non - meteoActive = Oui' =>
                [ Service::ROLE_ADMIN, [ 'exploitant' => false, 'meteoActive' => true ], 200 ],

            'ROLE_DME + Composant : Exploitant = Oui - meteoActive = Oui' =>
                [ Service::ROLE_DME, [ 'exploitant' => true, 'meteoActive' => true ], 200 ],
            'ROLE_DME + Composant : Exploitant = Oui - meteoActive = Non' =>
                [ Service::ROLE_DME, [ 'exploitant' => true, 'meteoActive' => false ], 400 ],
            'ROLE_DME + Composant : Exploitant = Non - meteoActive = Oui' =>
                [ Service::ROLE_DME, [ 'exploitant' => false, 'meteoActive' => true ], 200 ],

            'ROLE_INTERVENANT + Composant : Exploitant = Oui - meteoActive = Oui' =>
                [ Service::ROLE_INTERVENANT, [ 'exploitant' => true, 'meteoActive' => true ], 200 ],
            'ROLE_INTERVENANT + Composant : Exploitant = Oui - meteoActive = Non' =>
                [ Service::ROLE_INTERVENANT, [ 'exploitant' => true, 'meteoActive' => false ], 400 ],
            'ROLE_INTERVENANT + Composant : Exploitant = Non - meteoActive = Oui' =>
                [ Service::ROLE_INTERVENANT, [ 'exploitant' => false, 'meteoActive' => true ], 403 ],

            'ROLE_INVITE + Composant : Exploitant = Oui - meteoActive = Oui' =>
                [ Service::ROLE_INVITE, [ 'exploitant' => true, 'meteoActive' => true ], 403 ],
            'ROLE_INVITE + Composant : Exploitant = Oui - meteoActive = Non' =>
                [ Service::ROLE_INVITE, [ 'exploitant' => true, 'meteoActive' => false ], 403 ],
            'ROLE_INVITE + Composant : Exploitant = Non - meteoActive = Oui' =>
                [ Service::ROLE_INVITE, [ 'exploitant' => false, 'meteoActive' => true ], 403 ],
        ];
    }

    /**
     * Teste d'accès à la partie saisie de la météo en fonction de la période.
     *  (Uniquement 200, si la date passée est un début de semaine de météo)
     *
     * @dataProvider getAccesSaisiePeriodes
     *
     * @param string $periodeDebut
     * @param int    $statusCode
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testAccesSaisieParPeriodes(string $periodeDebut, int $statusCode)
    {
        // On crée le client et on récupère l'entity manager
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $service = $this->createService(Service::ROLE_ADMIN);

        // On crée un composant
        $composant = $this->createComposant($service, [ 'exploitant' => false, 'meteoActive' => true ]);

        // On se connecte
        static::loginAs($client, $service);

        // On vérifie que l'on a accès à la page de saisie d'un du composant
        $client->request(Request::METHOD_GET, '/meteo/modifier/' . $composant->getId() . '/' . $periodeDebut);
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
    }
    public function getAccesSaisiePeriodes(): array
    {
        return [
            '2021-01-06' => [ '20210106', 400 ],
            '2021-01-07' => [ '20210107', 200 ],
            '2021-01-10' => [ '20210110', 400 ],
            '2021-01-14' => [ '20210114', 200 ],
            '2021-01-15' => [ '20210115', 400 ],
        ];
    }

    /**
     * Teste la création, modification et suppression d'un évènement météo.
     */
    public function testSaisieCreationModificationSuppressionValide()
    {
        // On crée le client, notre service, notre composant et l'on se connecte ensuite
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $service = $this->createService(Service::ROLE_INTERVENANT);
        $composant = $this->createComposant($service, [ 'exploitant' => true, 'meteoActive' => true ]);
        static::loginAs($client, $service);

        // On prépare nos références
        $natureImpact = (new ImpactMeteo())->setLabel('Nature Impact');
        $em->persist($natureImpact);
        $typeOperation = (new MotifIntervention())->setLabel('Motif Intervention');
        $em->persist($typeOperation);
        $em->flush();

        // -- Ajout d'un évènement
        // On prépare notre "formulaire"
        $evenementMeteo = [
            'liste_evenements' => [
                'evenements' => [
                    [
                        'id' => '',
                        'action' => 'creation',
                        'debut' => '07/01/2021 00:00',
                        'fin' => '11/01/2021 09:30',
                        'impact' => $natureImpact->getId(),
                        'typeOperation' => $typeOperation->getId(),
                        'description' => 'Ceci est une description.',
                        'commentaire' => 'Ceci est un commentaire.',
                    ]
                ],
                '_token' => $client->getContainer()->get('security.csrf.token_manager')->getToken('liste_evenements')->getValue()
            ]
        ];

        // On effectue la saisie
        $client->request(Request::METHOD_POST, '/meteo/modifier/' . $composant->getId() . '/20210107', $evenementMeteo);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On teste que les données du nouvel évènement sont bien tous présent
        $nouvelEvenementMeteo = self::getEmRepository(Evenement::class, $client)->findOneBy([ 'composant' => $composant ]);
        $this->assertEquals(\DateTime::createFromFormat('d/m/Y H:i', '07/01/2021 00:00', new \DateTimeZone('Europe/Paris')), $nouvelEvenementMeteo->getDebut());
        $this->assertEquals(\DateTime::createFromFormat('d/m/Y H:i', '11/01/2021 09:30', new \DateTimeZone('Europe/Paris')), $nouvelEvenementMeteo->getFin());
        $this->assertEquals($natureImpact, $nouvelEvenementMeteo->getImpact());
        $this->assertEquals($typeOperation, $nouvelEvenementMeteo->getTypeOperation());
        $this->assertEquals('Ceci est une description.', $nouvelEvenementMeteo->getDescription());
        $this->assertEquals('Ceci est un commentaire.', $nouvelEvenementMeteo->getCommentaire());
        $this->assertEquals($service, $nouvelEvenementMeteo->getSaisiePar());

        // -- Modification d'un évènement
        // On prépare notre "formulaire"
        $evenementMeteo = [
            'liste_evenements' => [
                'evenements' => [
                    [
                        'id' => $nouvelEvenementMeteo->getId(),
                        'action' => 'edition',
                        'debut' => '08/01/2021 00:00',
                        'fin' => '12/01/2021 09:30',
                        'impact' => $natureImpact->getId(),
                        'typeOperation' => $typeOperation->getId(),
                        'description' => 'Ceci est une description 2.',
                        'commentaire' => 'Ceci est un commentaire 2.',
                    ]
                ],
                '_token' => $client->getContainer()->get('security.csrf.token_manager')->getToken('liste_evenements')->getValue()
            ]
        ];

        // On effectue la saisie
        $client->request(Request::METHOD_POST, '/meteo/modifier/' . $composant->getId() . '/20210107', $evenementMeteo);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On teste que les données du nouvel évènement sont bien tous présent
        $nouvelEvenementMeteo = self::getEmRepository(Evenement::class, $client)->find($nouvelEvenementMeteo->getId());
        $this->assertEquals(\DateTime::createFromFormat('d/m/Y H:i', '08/01/2021 00:00', new \DateTimeZone('Europe/Paris')), $nouvelEvenementMeteo->getDebut());
        $this->assertEquals(\DateTime::createFromFormat('d/m/Y H:i', '12/01/2021 09:30', new \DateTimeZone('Europe/Paris')), $nouvelEvenementMeteo->getFin());
        $this->assertEquals($natureImpact->getId(), $nouvelEvenementMeteo->getImpact()->getId());
        $this->assertEquals($typeOperation->getId(), $nouvelEvenementMeteo->getTypeOperation()->getId());
        $this->assertEquals('Ceci est une description 2.', $nouvelEvenementMeteo->getDescription());
        $this->assertEquals('Ceci est un commentaire 2.', $nouvelEvenementMeteo->getCommentaire());
        $this->assertEquals($service->getId(), $nouvelEvenementMeteo->getSaisiePar()->getId());

        // -- Suppression d'un évènement
        // On prépare notre "formulaire"
        $evenementMeteo = [
            'liste_evenements' => [
                'evenements' => [
                    [
                        'id' => $nouvelEvenementMeteo->getId(),
                        'action' => 'suppression',
                        'debut' => '08/01/2021 00:00',
                        'fin' => '12/01/2021 09:30',
                        'impact' => $natureImpact->getId(),
                        'typeOperation' => $typeOperation->getId(),
                        'description' => 'Ceci est une description 2.',
                        'commentaire' => 'Ceci est un commentaire 2.',
                    ]
                ],
                '_token' => $client->getContainer()->get('security.csrf.token_manager')->getToken('liste_evenements')->getValue()
            ]
        ];

        // On effectue la saisie
        $client->request(Request::METHOD_POST, '/meteo/modifier/' . $composant->getId() . '/20210107', $evenementMeteo);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On teste que les données du nouvel évènement sont bien tous présent
        $nouvelEvenementMeteo = self::getEmRepository(Evenement::class, $client)->find($nouvelEvenementMeteo->getId());
        $this->assertEquals(null, $nouvelEvenementMeteo);
    }
}
