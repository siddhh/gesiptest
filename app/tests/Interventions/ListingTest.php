<?php

namespace App\Tests\Interventions;

use App\Entity\Composant;
use App\Entity\Demande\HistoriqueStatus;
use App\Entity\DemandeIntervention;
use App\Entity\References\MotifIntervention;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatRenvoyee;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

class ListingTest extends UserWebTestCase
{
    /**
     * ------ Fonctions privée ------
     */
    /**
     * Fonction permettant d'initialiser un client avec un role prédéfini
     *
     * @param string $role
     *
     * @return KernelBrowser
     */
    private function initialisationClient(string $role): KernelBrowser
    {
        global $kernel;
        if ($role === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($role);
            $client = static::createClientLoggedAs($service);
        }
        $kernel = $client->getKernel();
        return $client;
    }

    /**
     * Fonction permettant d'effectuer un test d'accès sur une page de listing des demandes
     *
     * @param KernelBrowser $client
     * @param string        $url
     * @param int           $statusCode
     * @param string        $titre
     */
    private function accesListing(KernelBrowser &$client, string $url, int $statusCode, string $titre)
    {
        $client->request(Request::METHOD_GET, $url);
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains($titre);
        }
    }

    /**
     * On crée un service admin
     *
     * @return Service
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createServiceAdmin(): Service
    {
        $service = new Service();
        $service->setLabel("Service Admin");
        $service->setRoles([ Service::ROLE_ADMIN ]);
        $service->setEmail('service-admin@local.dev');
        $service->setMotdepasse('toto');
        $service->setEstPilotageDme(true);
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * On crée un composant
     *
     * @param Service $serviceExploitant
     *
     * @return Composant
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createComposant(Service $serviceExploitant): Composant
    {
        // On crée les objets annexe
        $usager = (new Usager())->setLabel('Usager');
        static::getEm()->persist($usager);
        $typeElement = (new TypeElement())->setLabel('Type element');
        static::getEm()->persist($typeElement);

        // On crée le composant
        $composant = new Composant();
        $composant->setLabel('Composant ' . uniqid());
        $composant->setUsager($usager);
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur");
        $composant->setMeteoActive(false);
        $composant->setExploitant($serviceExploitant);
        $composant->setEquipe($serviceExploitant);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * Fonction permettant de créer une demande d'intervention
     *
     * @param Composant $composant
     * @param Service   $serviceAdmin
     * @param string    $statut
     *
     * @return DemandeIntervention
     */
    private function createDemandeIntervention(Composant $composant, Service $serviceAdmin, string $statut, array $statutDonnees = []): DemandeIntervention
    {
        // On crée les objets annexes
        $motifIntervention = (new MotifIntervention())->setLabel('Motif Intervention ' . uniqid());
        static::getEm()->persist($motifIntervention);

        // On crée la demande d'intervention
        $maintenant = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $intervention = new DemandeIntervention();
        $intervention->setDemandePar($serviceAdmin);
        $intervention->setDemandeLe($maintenant);
        $intervention->setNumero($maintenant->format('YmdHis.v'));
        $intervention->setComposant($composant);
        $intervention->setDateDebut((clone $maintenant)->add(new \DateInterval('P5D')));
        $intervention->setDateFinMini((clone $maintenant)->add(new \DateInterval('P10D')));
        $intervention->setDateFinMax((clone $maintenant)->add(new \DateInterval('P11D')));
        $intervention->setStatus($statut);
        $intervention->setMotifIntervention($motifIntervention);
        $intervention->setNatureIntervention("Nature de l'intervention");
        $intervention->setPalierApplicatif(false);
        $intervention->setDescription("Description de l'intervention");
        $intervention->setDureeRetourArriere(24*60);
        $intervention->setStatusDonnees($statutDonnees);
        static::getEm()->persist($intervention);

        // On tire la chasse
        static::getEm()->flush();
        return $intervention;
    }

    /**
     * Fonction permettant de créer un bout d'historique d'une demande d'intervention
     * @param DemandeIntervention $demandeIntervention
     * @param string              $statut
     * @param array               $donnees
     *
     * @return HistoriqueStatus
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createHistoriqueDemande(DemandeIntervention $demandeIntervention, string $statut, array $donnees): HistoriqueStatus
    {
        // On crée notre objet représentant un bout d'historique de la demande
        $historique = new HistoriqueStatus();
        $historique->setDemande($demandeIntervention);
        $historique->setStatus($statut);
        $historique->setDonnees($donnees);
        static::getEm()->persist($historique);

        // On ajoute l'historique dans notre demande
        $demandeIntervention->addHistoriqueStatus($historique);

        // On tire la chasse
        static::getEm()->flush();
        return $historique;
    }

    /**
     * ------ Fonctions de tests ------
     */
    /**
     * Teste l'accès à la partie "Rechercher une demande"
     * @dataProvider getAccesRechercheRoles
     */
    public function testAccesRecherche(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/demandes/rechercher', $statusCode, 'Recherche des interventions');
    }
    public function getAccesRechercheRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      200 ]
        ];
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes en brouillon"
     * @dataProvider getAccesBrouillonRoles
     */
    public function testAccesBrouillon(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/demandes/brouillon/lister', $statusCode, 'Vos demandes enregistrées et non envoyées (brouillon)');
    }
    public function getAccesBrouillonRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      200 ]
        ];
    }

    /**
     * On teste que les demandes en brouillons s'affichent bien dans la page
     */
    public function testValiditeDesDonneesBrouillon()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatBrouillon::class);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/demandes/brouillon/lister');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes en cours d'analyse"
     * @dataProvider getAccesEnCoursDAnalyseRoles
     */
    public function testAccesEnCoursDAnalyse(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/gestion/demandes/analyse-en-cours', $statusCode, 'Liste des demandes en cours d\'analyse');
    }
    public function getAccesEnCoursDAnalyseRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ]
        ];
    }

    /**
     * On teste que les demandes en cours d'analyse s'affichent bien dans la page
     */
    public function testValiditeDesDonneesEnCoursDAnalyse()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatBrouillon::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/gestion/demandes/analyse-en-cours');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes renvoyées"
     * @dataProvider getAccesRenvoyeesRoles
     */
    public function testAccesRenvoyees(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/demandes/renvoyees/lister', $statusCode, 'Liste des demandes d\'interventions renvoyées');
    }
    public function getAccesRenvoyeesRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      200 ]
        ];
    }

    /**
     * On teste que les demandes renvoyées s'affichent bien dans la page
     */
    public function testValiditeDesDonneesRenvoyees()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatRenvoyee::class);
        $this->createHistoriqueDemande($demandeA, EtatRenvoyee::class, []);
        $this->createHistoriqueDemande($demandeA, EtatAnalyseEnCours::class, []);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/demandes/renvoyees/lister');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes en attente de consultation du CDB"
     * @dataProvider getAccesEnAttenteDeConsultationDuCdbRoles
     */
    public function testAccesEnAttenteDeConsultationDuCdb(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/gestion/demandes/attente-consultation-cdb', $statusCode, 'Liste des demandes en attente de consultation du Chef de Bureau');
    }
    public function getAccesEnAttenteDeConsultationDuCdbRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ]
        ];
    }

    /**
     * On teste que les demandes en attente de consultation du CDB s'affichent bien dans la page
     */
    public function testValiditeDesDonneesEnAttenteDeConsultationDuCdb()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatConsultationEnCours::class, [ 'avecCdb' => true ]);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/gestion/demandes/attente-consultation-cdb');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes en attente de réponse du CDB"
     * @dataProvider getAccesEnAttenteDeReponseDuCdbRoles
     */
    public function testAccesEnAttenteDeReponseDuCdb(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/gestion/demandes/attente-reponse-cdb', $statusCode, 'Liste des demandes en attente de réponse du Chef de Bureau');
    }
    public function getAccesEnAttenteDeReponseDuCdbRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ]
        ];
    }

    /**
     * On teste que les demandes en attente de consultation du CDB s'affichent bien dans la page
     */
    public function testValiditeDesDonneesEnAttenteDeReponseDuCdb()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatConsultationEnCoursCdb::class);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/gestion/demandes/attente-reponse-cdb');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes en attente de réponse"
     * @dataProvider getAccesEnAttenteDeReponseRoles
     */
    public function testAccesEnAttenteDeReponse(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/gestion/demandes/attente-reponse', $statusCode, 'Liste des demandes en attente de réponse');
    }
    public function getAccesEnAttenteDeReponseRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ]
        ];
    }

    /**
     * On teste que les demandes en attente de réponse s'affichent bien dans la page
     */
    public function testValiditeDesDonneesEnAttenteDeReponse()
    {
        // On crée le client
        $client = static::createClient();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatInstruite::class);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);
        $demandeC = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatConsultationEnCours::class);
        $demandeD = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatConsultationEnCoursCdb::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/gestion/demandes/attente-reponse');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
        $this->assertSelectorTextContains('*', $demandeC->getNumero());
        $this->assertSelectorTextContains('*', $demandeD->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Demandes acceptées"
     * @dataProvider getAccesAccepteesRoles
     */
    public function testAccesAcceptees(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/demandes/acceptees/lister', $statusCode, 'Demandes acceptées');
    }
    public function getAccesAccepteesRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      200 ]
        ];
    }

    /**
     * On teste que les demandes accordées s'affichent bien dans la page
     */
    public function testValiditeDesDonneesAcceptees()
    {
        // On crée le client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAccordee::class);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_GET, '/demandes/acceptees/lister');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Teste l'accès à la partie "Copier une demande"
     * @dataProvider getAccesCopierDemandeRoles
     */
    public function testAccesCopierDemande(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesListing($client, '/demandes/copier', $statusCode, 'Copie d\'une Demande d\'Intervention Programmée');
    }
    public function getAccesCopierDemandeRoles(): array
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
     * On teste que les demandes copiable s'affichent bien dans la page
     */
    public function testValiditeCopierDemande()
    {
        // On crée le client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composantA = $this->createComposant($serviceAdmin);
        $demandeA = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnnulee::class);
        $demandeB = $this->createDemandeIntervention($composantA, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        self::loginAs($client, $serviceAdmin);
        $client->request(Request::METHOD_POST, '/demandes/copier', [
            'recherche_intervention_copier' => [
                'demandePar' => $serviceAdmin->getId(),
                'composantConcerne' => $composantA->getId(),
                'motifIntervention' => $demandeA->getMotifIntervention()->getId(),
                'search' => '',
                '_token' => $client->getContainer()->get('security.csrf.token_manager')->getToken('recherche_intervention_copier')->getValue()
            ]
        ]);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSelectorTextContains('*', $demandeA->getNumero());
        $this->assertSelectorTextNotContains('*', $demandeB->getNumero());
    }
}
