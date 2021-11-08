<?php

namespace App\Tests\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\References\Mission;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Doctrine\ORM\EntityManager;

class ReferentielFluxTest extends UserWebTestCase
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
    private function createServiceDemandeur(): Service
    {
        $service = new Service();
        $service->setLabel("Service Demandeur");
        $service->setRoles([ Service::ROLE_INTERVENANT ]);
        $service->setEmail('service@local');
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
     * On crée un composant
     * @param string $label
     * @return Composant
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createComposant(string $label): Composant
    {
        // On crée les objets annexe
        $usager = (new Usager())->setLabel('Usager');
        static::getEm()->persist($usager);
        $typeElement = (new TypeElement())->setLabel('Type element');
        static::getEm()->persist($typeElement);

        // On crée le composant
        $composant = new Composant();
        $composant->setLabel($label);
        $composant->setUsager($usager);
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur");
        $composant->setMeteoActive(false);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * Fonction permettant de compter le nombre de demande pour des fins de tests
     * @param EntityManager $em
     * @param Service $serviceDemandeur
     * @param string $type
     * @param Composant $composantSource
     * @param Composant $composantTarget
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getCountDemandes(EntityManager $em, Service $serviceDemandeur, string $type, Composant $composantSource, Composant $composantTarget): int
    {
        return $em->getRepository(DemandeReferentielFlux::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where(
                'd.serviceDemandeur = :serviceDemandeur',
                'd.type = :type',
                'd.composantSource = :composantSource',
                'd.composantTarget = :composantTarget'
            )
            ->setParameter('serviceDemandeur', $serviceDemandeur)
            ->setParameter('type', $type)
            ->setParameter('composantSource', $composantSource)
            ->setParameter('composantTarget', $composantTarget)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ------ INDEX ------
     */
    /**
     * On test si l'on est bien redirigé à la page de d'index de création de demande, si pas de demandes en cours
     * pour le service connecté.
     */
    public function testIndexCreationDemandeSiPasDeDemandeEnCours(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/demandes');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertTrue($client->getResponse()->isRedirect('/fiabilisation/flux'));
    }

    /**
     * On test si l'on a une erreur 403 si nous sommes connecté en tant qu'invite
     */
    public function testIndexCreationDemandeSiPasDeDemandeEnCoursAccesInterdit(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();
        $serviceDemandeur->setRoles([ Service::ROLE_INVITE ]);
        self::getEm()->flush();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/demandes');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test si l'on affiche bien la page de liste des demandes effectuée par le service connecté, si celui en a
     * en cours
     */
    public function testIndexCreationDemandesSiDemandesEnCours(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();

        // On crée une demande
        $demande = new DemandeReferentielFlux();
        $demande->setType(DemandeReferentielFlux::AJOUT);
        $demande->setServiceDemandeur($serviceDemandeur);
        $demande->setComposantSource($this->createComposant("Composant A"));
        $demande->setComposantTarget($this->createComposant("Composant B"));
        static::getEm()->persist($demande);
        static::getEm()->flush();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/demandes');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }


    /**
     * ------ FLUX ENTRANTS / SORTANTS ------
     */
    /**
     * On test que nous retournons bien une erreur 403 quand le service connecté (non admin) souhaite afficher la page
     * Flux entrants pour un composant dont il n'est pas associé en tant que MOE ou MOE Délégué
     */
    public function testFluxEntrantsAccesInterditSiNonAdminEtNonMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceDemandeur = $this->createServiceDemandeur();
        $composant = $this->createComposant("Composant");

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/entrants/' . $composant->getId());
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test que nous retournons bien une erreur 200 quand le service connecté (admin) souhaite afficher la page
     * Flux entrants pour un composant dont il n'est pas associé en tant que MOE ou MOE Délégué
     */
    public function testFluxEntrantsAccesAutoriseSiAdminEtNonMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceAdmin = $this->createServiceAdmin();
        $composant = $this->createComposant("Composant");

        // On test le comportement
        $client = static::createClientLoggedAs($serviceAdmin);
        $client->request('GET', '/fiabilisation/flux/entrants/' . $composant->getId());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * On test l'affichage de la page Flux entrants pour un composant auquel le service connecté (non admin) est déclaré
     * en tant que MOE ou MOE Délégué
     */
    public function testFluxEntrantsAccesAutoriseSiNonAdminEtMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceDemandeur = $this->createServiceDemandeur();
        $composant = $this->createComposant("Composant");

        // on crée l'enregistrement du service dans l'annuaire
        $moeMission = (new Mission())->setLabel('MOE');
        static::getEm()->persist($moeMission);
        $annuaire = (new Composant\Annuaire())
            ->setService($serviceDemandeur)
            ->setComposant($composant)
            ->setMission($moeMission);
        static::getEm()->persist($annuaire);

        // On ajoute l'annuaire dans le composant
        $composant->addAnnuaire($annuaire);

        // On met à jour la base
        static::getEm()->flush();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/entrants/' . $composant->getId());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * On test que nous retournons bien une erreur 403 quand le service connecté (non admin) souhaite afficher la page
     * Flux sortants pour un composant dont il n'est pas associé en tant que MOE ou MOE Délégué
     */
    public function testFluxSortantsAccesInterditSiNonAdminEtNonMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceDemandeur = $this->createServiceDemandeur();
        $composant = $this->createComposant("Composant");

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/sortants/' . $composant->getId());
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test que nous retournons bien une erreur 200 quand le service connecté (admin) souhaite afficher la page
     * Flux sortants pour un composant dont il n'est pas associé en tant que MOE ou MOE Délégué
     */
    public function testFluxSortantsAccesAutoriseSiAdminEtNonMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceAdmin = $this->createServiceAdmin();
        $composant = $this->createComposant("Composant");

        // On test le comportement
        $client = static::createClientLoggedAs($serviceAdmin);
        $client->request('GET', '/fiabilisation/flux/sortants/' . $composant->getId());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * On test l'affichage de la page Flux sortants pour un composant auquel le service connecté (non admin) est déclaré
     * en tant que MOE ou MOE Délégué
     */
    public function testFluxSortantsAccesAutoriseSiNonAdminEtMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceDemandeur = $this->createServiceDemandeur();
        $composant = $this->createComposant("Composant");

        // on crée l'enregistrement du service dans l'annuaire
        $moeMission = (new Mission())->setLabel('MOE');
        static::getEm()->persist($moeMission);
        $annuaire = (new Composant\Annuaire())
            ->setService($serviceDemandeur)
            ->setComposant($composant)
            ->setMission($moeMission);
        static::getEm()->persist($annuaire);

        // On ajoute l'annuaire dans le composant
        $composant->addAnnuaire($annuaire);

        // On met à jour la base
        static::getEm()->flush();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/flux/sortants/' . $composant->getId());
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }


    /**
     * ------ CREATION DES DEMANDES D'AJOUTS / RETRAITS ------
     */
    /**
     * On test que nous retournons bien une erreur 403 quand le service connecté (non admin) souhaite envoyer les
     * demandes de modifications dans les flux entrants d'un composant auquel le service connecté n'est pas déclaré en
     * tant que MOE ou MOE Délégué
     */
    public function testCreationDemandesFluxEntrantsAccesInterditSiNonAdminEtNonMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceDemandeur = $this->createServiceDemandeur();
        $composant = $this->createComposant("Composant");

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'ajouts' => [],
            'retraits' => []
        ];

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('POST', '/ajax/fiabilisation/flux/entrants/' . $composant->getId(), $clientParams);
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test que les demandes dans les flux entrants d'un composant sont toutes bien enregistrées par un non admin
     * (ajout / retrait / annulation)
     */
    public function testCreationDemandesFluxEntrantsValideNonAdmin(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur et nos composants
        $serviceDemandeur = $this->createServiceDemandeur();
        $composantA = $this->createComposant("Composant A");
        $composantB = $this->createComposant("Composant B");
        $composantC = $this->createComposant("Composant C");
        $composantA->addComposantsImpacte($composantC);
        $composantD = $this->createComposant("Composant D");
        $em->flush();

        // on crée l'enregistrement du service dans l'annuaire
        $moeMission = (new Mission())->setLabel('MOE');
        $em->persist($moeMission);
        $annuaire = (new Composant\Annuaire())
            ->setService($serviceDemandeur)
            ->setComposant($composantA)
            ->setMission($moeMission);
        $em->persist($annuaire);

        // On ajoute l'annuaire dans le composant
        $composantA->addAnnuaire($annuaire);

        // On crée une demande qui sera annulée
        $demande = new DemandeReferentielFlux();
        $demande->setType(DemandeReferentielFlux::AJOUT);
        $demande->setServiceDemandeur($serviceDemandeur);
        $demande->setComposantTarget($composantD);
        $demande->setComposantSource($composantA);
        $em->persist($demande);

        // On tire la chasse pour bien être sûr que toutes les informations sont en bases
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'ajouts' => [
                $composantB->getId()
            ],
            'retraits' => [
                $composantC->getId()
            ]
        ];

        // On test si les demandes ne sont déjà ne base (au cas où!)
        $this->assertNull($demande->getAnnuleLe());
        $this->assertNull($demande->getAnnulePar());
        $this->assertEquals(
            0,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::AJOUT, $composantA, $composantB)
        );
        $this->assertEquals(
            0,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::RETRAIT, $composantA, $composantC)
        );

        // On se connecte en tant que $serviceDemandeur et on effectue la requête
        static::loginAs($client, $serviceDemandeur);
        $client->request('POST', '/ajax/fiabilisation/flux/entrants/' . $composantA->getId(), $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On test l'annulation de la demande déjà créée précédemment
        $this->assertEquals(
            1,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::AJOUT, $composantA, $composantB)
        );
        $this->assertEquals(
            1,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::RETRAIT, $composantA, $composantC)
        );

        // On rafraîchie la demande et on vérifie qu'elle a bien été annulée
        $em->refresh($demande);
        $this->assertTrue($demande->getAnnuleLe() !== null);
        $this->assertEquals($demande->getAnnulePar(), $serviceDemandeur);
    }

    /**
     * On test que nous retournons bien une erreur 403 quand le service connecté (non admin) souhaite envoyer les
     * demandes de modifications dans les flux sortants d'un composant auquel le service connecté n'est pas déclaré en
     * tant que MOE ou MOE Délégué
     */
    public function testCreationDemandesFluxSortantsAccesInterditSiNonAdminEtNonMoe(): void
    {
        // On crée notre service demandeur et notre composant
        $serviceDemandeur = $this->createServiceDemandeur();
        $composant = $this->createComposant("Composant");

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'ajouts' => [],
            'retraits' => []
        ];

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('POST', '/ajax/fiabilisation/flux/sortants/' . $composant->getId(), $clientParams);
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test que les demandes dans les flux sortants d'un composant sont toutes bien enregistrées
     * (ajout / retrait / annulation)
     */
    public function testCreationDemandesFluxSortantsValideNonAdmin(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur et nos composants
        $serviceDemandeur = $this->createServiceDemandeur();
        $composantA = $this->createComposant("Composant A");
        $composantB = $this->createComposant("Composant B");
        $composantC = $this->createComposant("Composant C");
        $composantA->addComposantsImpacte($composantC);
        $composantD = $this->createComposant("Composant D");
        $em->flush();

        // on crée l'enregistrement du service dans l'annuaire
        $moeMission = (new Mission())->setLabel('MOE');
        $em->persist($moeMission);
        $annuaire = (new Composant\Annuaire())
            ->setService($serviceDemandeur)
            ->setComposant($composantA)
            ->setMission($moeMission);
        $em->persist($annuaire);

        // On ajoute l'annuaire dans le composant
        $composantA->addAnnuaire($annuaire);

        // On crée une demande qui sera annulée
        $demande = new DemandeReferentielFlux();
        $demande->setType(DemandeReferentielFlux::AJOUT);
        $demande->setServiceDemandeur($serviceDemandeur);
        $demande->setComposantTarget($composantA);
        $demande->setComposantSource($composantD);
        $em->persist($demande);

        // On tire la chasse pour bien être sûr que toutes les informations sont en bases
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'ajouts' => [
                $composantB->getId()
            ],
            'retraits' => [
                $composantC->getId()
            ]
        ];

        // On test si les demandes ne sont déjà ne base (au cas où!)
        $this->assertNull($demande->getAnnuleLe());
        $this->assertNull($demande->getAnnulePar());
        $this->assertEquals(
            0,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::AJOUT, $composantA, $composantA)
        );
        $this->assertEquals(
            0,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::RETRAIT, $composantC, $composantA)
        );

        // On se connecte en tant que $serviceDemandeur et on effectue la requête
        static::loginAs($client, $serviceDemandeur);
        $client->request('POST', '/ajax/fiabilisation/flux/sortants/' . $composantA->getId(), $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On test l'annulation de la demande déjà créée précédemment
        $this->assertEquals(
            1,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::AJOUT, $composantB, $composantA)
        );
        $this->assertEquals(
            1,
            $this->getCountDemandes($em, $serviceDemandeur, DemandeReferentielFlux::RETRAIT, $composantC, $composantA)
        );

        // On rafraîchie la demande et on vérifie qu'elle a bien été annulée
        $em->refresh($demande);
        $this->assertTrue($demande->getAnnuleLe() !== null);
        $this->assertEquals($demande->getAnnulePar(), $serviceDemandeur);
    }

    /**
     * ------ MISE A JOUR DU REFERENTIEL DES FLUX (par admin) ------
     */
    /**
     * On test que l'on puisse modifier directement le référentiel des flux entrants d'un composant directement si admin
     */
    public function testMajAdminFluxEntrants(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur et nos composants
        /** @var Service $serviceDemandeur */
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant("Composant A");
        $composantB = $this->createComposant("Composant B");
        $composantC = $this->createComposant("Composant C");
        $composantA->addImpactesParComposant($composantC);
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'ajouts' => [
                $composantB->getId()
            ],
            'retraits' => [
                $composantC->getId()
            ]
        ];

        // On test que les composants sont bien pas présent ou le sont avant la requête
        $this->assertNotContains($composantB, $composantA->getFluxEntrants());
        $this->assertContains($composantC, $composantA->getFluxEntrants());

        // On se connecte en tant que $serviceDemandeur et on effectue la requête
        static::loginAs($client, $serviceAdmin);
        $client->request('POST', '/ajax/fiabilisation/flux/entrants/' . $composantA->getId(), $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On test que les composants sont bien pas présent ou le sont après la requête
        $em->refresh($composantA);
        $this->assertContains($composantB, $composantA->getFluxEntrants());
        $this->assertNotContains($composantC, $composantA->getFluxEntrants());
    }

    /**
     * On test que l'on puisse modifier directement le référentiel des flux sortants d'un composant directement si admin
     */
    public function testMajAdminFluxSortants(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur et nos composants
        /** @var Service $serviceDemandeur */
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant("Composant A");
        $composantB = $this->createComposant("Composant B");
        $composantC = $this->createComposant("Composant C");
        $composantA->addComposantsImpacte($composantC);
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'ajouts' => [
                $composantB->getId()
            ],
            'retraits' => [
                $composantC->getId()
            ]
        ];

        // On test que les composants sont bien pas présent ou le sont avant la requête
        $this->assertNotContains($composantB, $composantA->getFluxSortants());
        $this->assertContains($composantC, $composantA->getFluxSortants());

        // On se connecte en tant que $serviceDemandeur et on effectue la requête
        static::loginAs($client, $serviceAdmin);
        $client->request('POST', '/ajax/fiabilisation/flux/sortants/' . $composantA->getId(), $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On test que les composants sont bien pas présent ou le sont après la requête
        $em->refresh($composantA);
        $this->assertContains($composantB, $composantA->getFluxSortants());
        $this->assertNotContains($composantC, $composantA->getFluxSortants());
    }


    /**
     * ------ GESTION DES DEMANDES (par admin) ------
     */
    /**
     * On test si les services non admin et non dme ne peuvent pas accéder aux pages réservées
     */
    public function testGestionDesDemandesAccesInterdit(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/gestion/fiabilisation/flux');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
        $client->request('GET', '/gestion/fiabilisation/flux/liste');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test si les services admin ou dme peuvent accéder aux pages réservées
     */
    public function testGestionDesDemandesAccesAutorise(): void
    {
        // On crée notre service demandeur
        $serviceAdmin = $this->createServiceAdmin();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceAdmin);
        $client->request('GET', '/gestion/fiabilisation/flux');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $client->request('GET', '/gestion/fiabilisation/flux/liste');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * On test la soumission d'une acceptation de demandes
     */
    public function testGestionDesDemandesAcceptationDemande(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();
        // On crée notre service admin
        $serviceAdmin = $this->createServiceAdmin();

        // On crée des composants
        $composantA = $this->createComposant("Composant A");
        $composantB = $this->createComposant("Composant B");
        $composantC = $this->createComposant("Composant C");
        $composantA->addComposantsImpacte($composantC);

        // On crée deux demandes : un ajout et un retrait
        $demandeAjout = new DemandeReferentielFlux();
        $demandeAjout->setType(DemandeReferentielFlux::AJOUT);
        $demandeAjout->setServiceDemandeur($serviceDemandeur);
        $demandeAjout->setComposantTarget($composantA);
        $demandeAjout->setComposantSource($composantB);
        $em->persist($demandeAjout);

        $demandeRetrait = new DemandeReferentielFlux();
        $demandeRetrait->setType(DemandeReferentielFlux::RETRAIT);
        $demandeRetrait->setServiceDemandeur($serviceDemandeur);
        $demandeRetrait->setComposantTarget($composantA);
        $demandeRetrait->setComposantSource($composantC);
        $em->persist($demandeRetrait);

        // On tire la chasse pour être sûr que les infos sont bien en base
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'demandeIds' => [
                $demandeAjout->getId(),
                $demandeRetrait->getId()
            ],
            'comment' => 'Un commentaire.'
        ];

        // On test le comportement
        static::loginAs($client, $serviceAdmin);
        $client->request('PUT', '/ajax/fiabilisation/flux/demandes/accept', $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On test le résultat de la demande d'ajout
        $em->refresh($demandeAjout);
        $this->assertNotNull($demandeAjout->getAccepteLe());
        $this->assertEquals($serviceAdmin, $demandeAjout->getAcceptePar());
        $this->assertEquals("Un commentaire.", $demandeAjout->getCommentaire());

        // On test le résultat de la demande de retrait
        $em->refresh($demandeRetrait);
        $this->assertNotNull($demandeRetrait->getAccepteLe());
        $this->assertEquals($serviceAdmin, $demandeRetrait->getAcceptePar());
        $this->assertEquals("Un commentaire.", $demandeRetrait->getCommentaire());

        // On test l'application des demandes
        $em->refresh($composantA);
        $this->assertContains($composantB, $composantA->getFluxSortants());
        $this->assertNotContains($composantC, $composantA->getFluxSortants());

        // On vérifie l'envoi du mail
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $serviceDemandeur->getEmail());
        $subContents = [
            $composantA->getLabel(),
            $composantB->getLabel(),
            $composantC->getLabel(),
            'accordée',
            'Ajout',
            'Retrait',
            'Un commentaire.'
        ];
        foreach ($subContents as $subContent) {
            $this->assertEmailTextBodyContains($email, $subContent);
            $this->assertEmailHtmlBodyContains($email, $subContent);
        }
    }

    /**
     * On test la soumission d'un refus de demandes
     */
    public function testGestionDesDemandesRefusDemande(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();
        // On crée notre service admin
        $serviceAdmin = $this->createServiceAdmin();

        // On crée des composants
        $composantA = $this->createComposant("Composant A");
        $composantB = $this->createComposant("Composant B");
        $composantC = $this->createComposant("Composant C");
        $composantA->addComposantsImpacte($composantC);

        // On crée deux demandes : un ajout et un retrait
        $demandeAjout = new DemandeReferentielFlux();
        $demandeAjout->setType(DemandeReferentielFlux::AJOUT);
        $demandeAjout->setServiceDemandeur($serviceDemandeur);
        $demandeAjout->setComposantTarget($composantA);
        $demandeAjout->setComposantSource($composantB);
        $em->persist($demandeAjout);

        $demandeRetrait = new DemandeReferentielFlux();
        $demandeRetrait->setType(DemandeReferentielFlux::RETRAIT);
        $demandeRetrait->setServiceDemandeur($serviceDemandeur);
        $demandeRetrait->setComposantTarget($composantA);
        $demandeRetrait->setComposantSource($composantC);
        $em->persist($demandeRetrait);

        // On tire la chasse pour être sûr que les infos sont bien en base
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $clientParams = [
            'demandeIds' => [
                $demandeAjout->getId(),
                $demandeRetrait->getId()
            ],
            'comment' => 'Un commentaire.'
        ];

        // On test le comportement
        static::loginAs($client, $serviceAdmin);
        $client->request('PUT', '/ajax/fiabilisation/flux/demandes/refuse', $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // On test le résultat de la demande d'ajout
        $em->refresh($demandeAjout);
        $this->assertNotNull($demandeAjout->getRefuseLe());
        $this->assertEquals($serviceAdmin, $demandeAjout->getRefusePar());
        $this->assertEquals("Un commentaire.", $demandeAjout->getCommentaire());

        // On test le résultat de la demande de retrait
        $em->refresh($demandeRetrait);
        $this->assertNotNull($demandeRetrait->getRefuseLe());
        $this->assertEquals($serviceAdmin, $demandeRetrait->getRefusePar());
        $this->assertEquals("Un commentaire.", $demandeRetrait->getCommentaire());

        // On test la NON application des demandes
        $em->refresh($composantA);
        $this->assertNotContains($composantB, $composantA->getFluxSortants());
        $this->assertContains($composantC, $composantA->getFluxSortants());

        // On vérifie l'envoi du mail
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $serviceDemandeur->getEmail());
        $subContents = [
            $composantA->getLabel(),
            $composantB->getLabel(),
            $composantC->getLabel(),
            'refusée',
            'Ajout',
            'Retrait',
            'Un commentaire.'
        ];
        foreach ($subContents as $subContent) {
            $this->assertEmailTextBodyContains($email, $subContent);
            $this->assertEmailHtmlBodyContains($email, $subContent);
        }
    }
}
