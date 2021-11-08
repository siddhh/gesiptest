<?php

namespace App\Tests\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\Service;
use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\References\Mission;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Tests\UserWebTestCase;

class PerimetreApplicatifTest extends UserWebTestCase
{

    private const SUFFIX = 'perimetre-applicatif';

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
        $service->setLabel("Service Demandeur " . self::SUFFIX);
        $service->setRoles([ Service::ROLE_INTERVENANT ]);
        $service->setEmail('service-perimetre-applicatif@local');
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
        $service->setLabel("Service Admin Perimetre Applicatif");
        $service->setRoles([ Service::ROLE_ADMIN ]);
        $service->setEmail('admin-perimetre-applicatif@local');
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
        $usager = (new Usager())->setLabel('Usager Perimetre Applicatif');
        static::getEm()->persist($usager);
        $typeElement = (new TypeElement())->setLabel('Type element Perimetre Applicatif');
        static::getEm()->persist($typeElement);

        // On crée le composant
        $composant = new Composant();
        $composant->setLabel($label);
        $composant->setUsager($usager);
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur Perimetre Applicatif");
        $composant->setMeteoActive(false);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * ------ INDEX ------
     */
    /**
     * On test si l'on affiche bien la page permettant de réaliser des demandes de modification du périmètre applicatif
     */
    public function testApplicatifIndexCreationDemandes(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/applicatif/demandes');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * On test si l'on a une erreur 403 si nous sommes connecté en tant qu'invite
     */
    public function testApplicatifIndexCreationDemandesAccesInterdit(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();
        $serviceDemandeur->setRoles([ Service::ROLE_INVITE ]);
        self::getEm()->flush();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/fiabilisation/applicatif/demandes');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * ------ GESTION DES DEMANDES (par admin) ------
     */
    /**
     * On test si les services non admin et non dme ne peuvent pas accéder aux pages réservées
     */
    public function testGestionDesDemandesApplicatifAccesInterdit(): void
    {
        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceDemandeur);
        $client->request('GET', '/gestion/fiabilisation/applicatif');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
        $client->request('GET', '/gestion/fiabilisation/applicatif/liste');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }

    /**
     * On test si les services admin ou dme peuvent accéder aux pages réservées
     */
    public function testGestionDesDemandesApplicatifAccesAutorise(): void
    {
        // On crée notre service demandeur
        $serviceAdmin = $this->createServiceAdmin();

        // On test le comportement
        $client = static::createClientLoggedAs($serviceAdmin);
        $client->request('GET', '/gestion/fiabilisation/applicatif');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $client->request('GET', '/gestion/fiabilisation/applicatif/liste');
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

        // On crée un composant
        $composant = $this->createComposant('Composant' . self::SUFFIX);

        // On crée des missions
        $missions = [];
        for ($i = 0; $i < 3; $i++) {
            $mission = new Mission();
            $mission->setLabel('Mission ' . $i);
            static::getEm()->persist($mission);
            $missions[] = $mission;
            // On ajoute des entrée dans l'annuaire du composant
            if (in_array($i, [0, 2])) {
                $annuaire = new Annuaire();
                $annuaire->setService($serviceDemandeur);
                $annuaire->setMission($mission);
                $composant->addAnnuaire($annuaire);
            }
        }

        // On crée deux demandes : un ajout et un retrait
        $demandeAjout = new DemandePerimetreApplicatif();
        $demandeAjout->setType(DemandePerimetreApplicatif::AJOUT);
        $demandeAjout->setServiceDemandeur($serviceDemandeur);
        $demandeAjout->setComposant($composant);
        $demandeAjout->setMission($missions[1]);
        $em->persist($demandeAjout);
        $demandeRetrait = new DemandePerimetreApplicatif();
        $demandeRetrait->setType(DemandePerimetreApplicatif::RETRAIT);
        $demandeRetrait->setServiceDemandeur($serviceDemandeur);
        $demandeRetrait->setComposant($composant);
        $demandeRetrait->setMission($missions[2]);
        $em->persist($demandeRetrait);

        // On tire la chasse pour être sûr que les infos sont bien en base
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $comment = 'Un commentaire d acceptation ' . self::SUFFIX;
        $clientParams = [
            'demandeIds' => [
                $demandeAjout->getId(),
                $demandeRetrait->getId()
            ],
            'comment' => $comment
        ];

        // On test le comportement
        static::loginAs($client, $serviceAdmin);
        $client->request('PUT', '/ajax/fiabilisation/applicatif/demandes/accept', $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($data['status'], 'success');

        // On test le résultat de la demande d'ajout
        $em->refresh($demandeAjout);
        $this->assertNotNull($demandeAjout->getAccepteLe());
        $this->assertEquals($serviceAdmin, $demandeAjout->getAcceptePar());
        $this->assertEquals($comment, $demandeAjout->getCommentaire());

        // On test le résultat de la demande de retrait
        $em->refresh($demandeRetrait);
        $this->assertNotNull($demandeRetrait->getAccepteLe());
        $this->assertEquals($serviceAdmin, $demandeRetrait->getAcceptePar());
        $this->assertEquals($comment, $demandeRetrait->getCommentaire());

        // On vérifie l'envoi du mail
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $serviceDemandeur->getEmail());
        $subContents = [
            'Composant' . self::SUFFIX,
            'accordée',
            'Ajout',
            $missions[1]->getLabel(),
            'Retrait',
            $missions[2]->getLabel(),
            $comment
        ];
        foreach ($subContents as $subContent) {
            $this->assertEmailTextBodyContains($email, $subContent);
            $this->assertEmailHtmlBodyContains($email, $subContent);
        }
    }

    /**
     * On test le rejet de demandes
     */
    public function testGestionDesDemandesRejetDemande(): void
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service demandeur
        $serviceDemandeur = $this->createServiceDemandeur();
        // On crée notre service admin
        $serviceAdmin = $this->createServiceAdmin();

        // On crée un composant
        $composant = $this->createComposant('Composant' . self::SUFFIX);

        // On crée des missions
        $missions = [];
        for ($i = 0; $i < 3; $i++) {
            $mission = new Mission();
            $mission->setLabel('Mission ' . $i);
            static::getEm()->persist($mission);
            $missions[] = $mission;
            // On ajoute des entrée dans l'annuaire du composant
            if (in_array($i, [0, 2])) {
                $annuaire = new Annuaire();
                $annuaire->setService($serviceDemandeur);
                $annuaire->setMission($mission);
                $composant->addAnnuaire($annuaire);
            }
        }

        // On crée deux demandes : un ajout et un retrait
        $demandeAjout = new DemandePerimetreApplicatif();
        $demandeAjout->setType(DemandePerimetreApplicatif::AJOUT);
        $demandeAjout->setServiceDemandeur($serviceDemandeur);
        $demandeAjout->setComposant($composant);
        $demandeAjout->setMission($missions[1]);
        $em->persist($demandeAjout);
        $demandeRetrait = new DemandePerimetreApplicatif();
        $demandeRetrait->setType(DemandePerimetreApplicatif::RETRAIT);
        $demandeRetrait->setServiceDemandeur($serviceDemandeur);
        $demandeRetrait->setComposant($composant);
        $demandeRetrait->setMission($missions[2]);
        $em->persist($demandeRetrait);

        // On tire la chasse pour être sûr que les infos sont bien en base
        $em->flush();

        // On défini les paramètres à envoyer à la route
        $comment = 'Un commentaire de refus ' . self::SUFFIX;
        $clientParams = [
            'demandeIds' => [
                $demandeAjout->getId(),
                $demandeRetrait->getId()
            ],
            'comment' => $comment
        ];

        // On test le comportement
        static::loginAs($client, $serviceAdmin);
        $client->request('PUT', '/ajax/fiabilisation/applicatif/demandes/refuse', $clientParams);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($data['status'], 'success');

        // On test le résultat de la demande d'ajout
        $em->refresh($demandeAjout);
        $this->assertNotNull($demandeAjout->getRefuseLe());
        $this->assertEquals($serviceAdmin, $demandeAjout->getRefusePar());
        $this->assertEquals($comment, $demandeAjout->getCommentaire());

        // On test le résultat de la demande de retrait
        $em->refresh($demandeRetrait);
        $this->assertNotNull($demandeRetrait->getRefuseLe());
        $this->assertEquals($serviceAdmin, $demandeRetrait->getRefusePar());
        $this->assertEquals($comment, $demandeRetrait->getCommentaire());

        // On vérifie l'envoi du mail
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', $serviceDemandeur->getEmail());
        $subContents = [
            'Composant' . self::SUFFIX,
            'refusée',
            'Ajout',
            $missions[1]->getLabel(),
            'Retrait',
            $missions[2]->getLabel(),
            $comment
        ];
        foreach ($subContents as $subContent) {
            $this->assertEmailTextBodyContains($email, $subContent);
            $this->assertEmailHtmlBodyContains($email, $subContent);
        }
    }
}
