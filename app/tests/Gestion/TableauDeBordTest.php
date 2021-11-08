<?php

namespace App\Tests\Gestion;

use App\Entity\Service;
use App\Entity\DemandeIntervention;
use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\References\Mission;
use App\Entity\References\MotifIntervention;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatTerminee;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class TableauDeBordTest extends UserWebTestCase
{
    /**
     * Liste des constantes passées en paramètre d'une création de demande
     */
    private const PAS_SERVICE_EXPLOITANT    =   'pas service exploitant';   // le service connecté n'est pas le service exploitant
    private const PAS_SERVICE_DEMANDEUR     =   'pas service demandeur';   // le service connecté n'est pas le service demandeur
    private const AUCUN_ROLE                =   'aucun role';   // le service connecté n'est ni le service exploitant ni le service demandeur
    private const PAS_D_EQUIPE              =   'pas dequipe';  // le composant n'a pas d'équipe pilote
    private const AUTRE_EQUIPE              =   'autre equipe'; // le composant a comme équipe pilote un service qui n'est pas équipe pilote

    private function createDemande(array $configuration) : DemandeIntervention
    {
        // On récupère l'entity manager, le même que pour le test
        $em = self::getEm();

        // On définit les pré-requis
        $motif = $em->getRepository(MotifIntervention::class)->findOneBy([]);
        $etat = $configuration[0];

        // On définit les services
        $serviceExploitant = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service Intervenant']);
        $serviceDemandeur = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service Intervenant']);
        $equipeComposant = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service DME']);
        if (isset($configuration[2])) {
            if ($configuration[2] == self::PAS_SERVICE_EXPLOITANT || $configuration[2] == self::AUCUN_ROLE) {
                $serviceExploitant = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service invité']);
            }
            if ($configuration[2] == self::PAS_SERVICE_DEMANDEUR || $configuration[2] == self::AUCUN_ROLE) {
                $serviceDemandeur = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service invité']);
            }
            if ($configuration[2] == self::PAS_D_EQUIPE) {
                $equipeComposant = null;
            }
            if ($configuration[2] == self::AUTRE_EQUIPE) {
                $equipeComposant = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service invité']);
            }
        }

        // On définit le composant
        $composant = $em->getRepository(Composant::class)->findOneBy([]);
        $composant->setEquipe($equipeComposant);

        // On crée l'annuaire
        $annuaire = new Annuaire();
        $annuaire->setService($serviceExploitant);
        $annuaire->setComposant($composant);
        $mission = $em->getRepository(Mission::class)->findOneBy(['label' => 'ES Exploitant Système']);
        $annuaire->setMission($mission);
        $em->persist($annuaire);

        // On génère la demande
        $demande = new DemandeIntervention();
        $demande->setNumero(microtime());
        $demande->setDemandeLe(new \DateTime());
        $demande->setNatureIntervention(DemandeIntervention::NATURE_URGENT);
        $demande->setPalierApplicatif(rand(0, 1) > 0);
        $demande->setDescription('ceci est un test');
        $startIntervention = time();
        $minEndIntervention = $startIntervention;
        $maxEndIntervention = $minEndIntervention;
        $demande->setDateDebut(new \DateTime("@$startIntervention"));
        $demande->setDateFinMini(new \DateTime("@$minEndIntervention"));
        $demande->setDateFinMax(new \DateTime("@$maxEndIntervention"));
        $demande->setDureeRetourArriere(150);
        $demande->setMotifIntervention($motif);
        $demande->setDemandePar($serviceDemandeur);
        $demande->setComposant($composant);
        $demande->setStatus($etat);
        $demande->addService($annuaire);

        $em->persist($demande);
        $em->flush();
        return $demande;
    }

     /**
     * Test du filtre principal pour chaque valeur sélectionnée avec le rôle DME
     * @dataProvider dpFiltrePrincipalPourDme
     */
    public function testFiltrePrincipalPourDme(array $configurationDemande, bool $demandeAffichee)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service invité et on se connecte avec
        $service = self::getOneServiceFromRole(Service::ROLE_DME);
        self::loginAs($client, $service);

        // On crée la demande et récupère le token
        $demande = $this->createDemande($configurationDemande);
        $token = $client->getContainer()->get('security.csrf.token_manager')
                    ->getToken('recherche_demande_intervention')->getValue();

        // On génère la réponse
        $reponse = $client->request(Request::METHOD_POST, '/', [
            'recherche_demande_intervention'    => [
                'status'    => $configurationDemande[1],
                '_token'    =>  $token
            ]
        ]);

        // On teste si la demande doit être affichée ou non
        if ($demandeAffichee) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        } else {
            $this->assertSelectorTextNotContains('*', $demande->getNumero());
        }
    }

    /**
     * DataProvider du test filtre principal pour DME
     * 1) 6 tests avec un composant dont il est l'équipe
     * 2) 6 tests avec un composant dont il n'est pas l'équipe
     * 3) 6 tests avec un composant sans équipe
     */
    public function dpFiltrePrincipalPourDme() : array
    {
        return [
            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    null
                ],
                true
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    null
                ],
                true
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    null
                ],
                true
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    null
                ],
                true
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    null,
                ],
                true
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    null,
                ],
                true
            ],

            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    self::AUTRE_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    self::AUTRE_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    self::AUTRE_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    self::AUTRE_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    self::AUTRE_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    self::AUTRE_EQUIPE
                ],
                true
            ],

            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    self::PAS_D_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    self::PAS_D_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite'
                    ,self::PAS_D_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    self::PAS_D_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    self::PAS_D_EQUIPE
                ],
                true
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    self::PAS_D_EQUIPE
                ],
                true
            ],
        ];
    }

     /**
     * Test du filtre principal pour chaque valeur sélectionnée avec le rôle INTERVENANT
     * @dataProvider dpFiltrePrincipalPourIntervenant
     */
    public function testFiltrePrincipalPourIntervenant(array $configurationDemande, bool $demandeAffichee)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service invité et on se connecte avec
        $service = self::getOneServiceFromRole(Service::ROLE_INTERVENANT);
        self::loginAs($client, $service);

        // On crée la demande et on récupère le token de session
        $demande = $this->createDemande($configurationDemande);
        $token = $client->getContainer()->get('security.csrf.token_manager')
                    ->getToken('recherche_demande_intervention')->getValue();

        // On génère la réponse
        $client->request(Request::METHOD_POST, '/', [
            'recherche_demande_intervention'    => [
                'status'    => $configurationDemande[1],
                '_token'    =>  $token
            ]
        ]);

        // On teste si la demande doit être affichée ou non
        if ($demandeAffichee) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        } else {
            $this->assertSelectorTextNotContains('*', $demande->getNumero());
        }
    }

    /**
     * DataProvider du test filtre principal intervenant
     * Colonne 0:l'état', col1:le statut pour le template, col 2:le service demandeur
     * 1) 6 tests en tant que demandeur uniquement
     * 2) 6 tests en tant qu'exploitant uniquement
     * 3) 6 tests où il n est ni exploitant ni demandeur
     */
    public function dpFiltrePrincipalPourIntervenant() : array
    {
        return [
            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    self::PAS_SERVICE_EXPLOITANT
                ],
                true
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    self::PAS_SERVICE_EXPLOITANT
                ],
                true
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    self::PAS_SERVICE_EXPLOITANT
                ],
                true
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    self::PAS_SERVICE_EXPLOITANT
                ],
                true
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    self::PAS_SERVICE_EXPLOITANT
                ],
                true
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    self::PAS_SERVICE_EXPLOITANT
                ],
                false
            ],

            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    self::PAS_SERVICE_DEMANDEUR
                ],
                true
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    self::PAS_SERVICE_DEMANDEUR
                ],
                true
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    self::PAS_SERVICE_DEMANDEUR
                ],
                true
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    self::PAS_SERVICE_DEMANDEUR
                ],
                true
            ],

            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    self::AUCUN_ROLE
                ],
                false
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    self::AUCUN_ROLE
                ],
                false
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    self::AUCUN_ROLE
                ],
                false
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    self::AUCUN_ROLE
                ],
                false
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    self::AUCUN_ROLE
                ],
                false
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    self::AUCUN_ROLE
                ],
                false
            ],
        ];
    }

     /**
     * Test du filtre principal pour chaque valeur sélectionnée avec le rôle Admin
     * @dataProvider dpFiltrePrincipalPourAdmin
     */
    public function testFiltrePrincipalPourAdmin(array $configurationDemande, bool $demandeAffichee)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service invité et on se connecte avec
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        self::loginAs($client, $service);

        // On crée la demande et on récupère le token de session
        $demande = $this->createDemande($configurationDemande);
        $token =  $client->getContainer()->get('security.csrf.token_manager')
                    ->getToken('recherche_demande_intervention')->getValue();

        // On génère la réponse
        $reponse = $client->request(Request::METHOD_POST, '/', [
            'recherche_demande_intervention'    => [
                'status'    => $configurationDemande[1],
                '_token'    =>  $token
            ]
        ]);

        // On teste si la demande doit être affichée ou non
        if ($demandeAffichee) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        } else {
            $this->assertSelectorTextNotContains('*', $demande->getNumero());
        }
    }

    /**
     * DataProvider du test filtre principal pour Admin
     */
    public function dpFiltrePrincipalPourAdmin() : array
    {
        return [
            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    null
                ],
                true
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    null
                ],
                true
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    null
                ],
                true
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    null
                ],
                true
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    null
                ],
                true
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    null
                ],
                true
            ],
        ];
    }

     /**
     * Test du filtre principal pour chaque valeur sélectionnée avec le rôle INVITE
     * @dataProvider dpFiltrePrincipalPourInvite
     */
    public function testFiltrePrincipalPourInvite(array $configurationDemande, bool $demandeAffichee)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service invité et on se connecte avec
        $service = self::getOneServiceFromRole(Service::ROLE_INVITE);
        self::loginAs($client, $service);

        // On crée la demande et on génère la réponse
        $demande = $this->createDemande($configurationDemande);
        $reponse = $client->request(Request::METHOD_POST, '/', [
            'recherche_demande_intervention'    => [
                'status'    => $configurationDemande[1]
            ]
        ]);

        // On teste si la demande doit être affichée ou non
        if ($demandeAffichee) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        } else {
            $this->assertSelectorTextNotContains('*', $demande->getNumero());
        }
    }

    /**
     * DataProvider du test filtre principal invite
     */
    public function dpFiltrePrincipalPourInvite() : array
    {
        return [
            [
                [
                    EtatInterventionEnCours::class,
                    'EtatInterventionEnCours',
                    null
                ],
                true
            ],
            [
                [
                    EtatAnalyseEnCours::class,
                    'EtatAnalyseEnCours',
                    null
                ],
                false
            ],
            [
                [
                    EtatConsultationEnCoursCdb::class,
                    'EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite',
                    null
                ],
                false
            ],
            [
                [
                    EtatRenvoyee::class,
                    'EtatRenvoyee',
                    null
                ],
                false
            ],
            [
                [
                    EtatAccordee::class,
                    'EtatAccordee',
                    null
                ],
                false
            ],
            [
                [
                    EtatSaisirRealise::class,
                    'EtatSaisirRealise',
                    null
                ],
                false
            ],
        ];
    }

    /**
     * Test de présence de la mention Aucune demande à afficher
     * @dataProvider getAccesParRoles
     */
    public function testMessageAucuneDemande(string $role)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        $em = self::$entityManager = self::getEm($client);

        // On met un statut interdit à toutes les demandes
        $listeDemandes = $em->getRepository(DemandeIntervention::class)->findAll();
        foreach ($listeDemandes as $demande) {
            $demande->setStatus(EtatBrouillon::class);
            $em->persist($demande);
        }
        $em->flush();

        // On récupère le service et on se connecte avec
        $service = self::getOneServiceFromRole($role);
        self::loginAs($client, $service);
        $client->request('GET', '/');

        $this->assertSelectorTextContains('*', 'Aucune demande');
    }

    /**
     * Test de l'absence de demande aux statuts:'Demande refusée', 'Demande annulée', 'Intervention réussie', 'Intervention en échec','Brouillon enregistré'
     * pour le rôle ADMIN seulement
     * @dataProvider dpAbsenceDeDemandeFermee
     */
    public function testAbsenceDeDemandeFermee(array $statutInterdit)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service invité et on se connecte avec
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        self::loginAs($client, $service);

        // On crée la demande et requête le tableau de bord
        $this->createDemande([$statutInterdit[0]]);
        $client->request('GET', '/');

        // On teste l'absence de libellé de statut correspondant à un statut interdit
        $this->assertSelectorTextNotContains('main .table', $statutInterdit[1]);
    }

    /**
     * DataProvider du test d'absence de demande fermée
     */
    public function dpAbsenceDeDemandeFermee() : array
    {
        return [
            [ [EtatRefusee::class,'Refusée'] ],
            [ [EtatAnnulee::class,'Annulée'] ],
            [ [EtatInterventionReussie::class,'Réussie'] ],
            [ [EtatInterventionEchouee::class,'Échouée'] ],
            [ [EtatBrouillon::class,'Brouillon'] ],
            [ [EtatTerminee::class,'Terminée'] ],
        ];
    }

     /**
     * Test de présence de la colonne Pilote ou de son absence selon le rôle
     * @dataProvider getAccesParRoles
     */
    public function testPresenceColonnePilote(string $role)
    {
        $this->markTestSkipped("Bug crawler ? (Test failed 1/50 tries)");
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service et on se connecte avec
        $service = self::getOneServiceFromRole($role);
        self::loginAs($client, $service);
        $client->request('GET', '/');

        // Si le service connecté est Admin ou Dme, la colonne Pilote est affichée, autrement non
        if (in_array($role, [Service::ROLE_ADMIN,Service::ROLE_DME])) {
            $this->assertSelectorTextContains('thead', 'Pilote');
        } else {
            $this->assertSelectorTextNotContains('thead', 'Pilote');
        }
    }

    /**
     * Test de présence du filtre Demandes urgentes
     * @dataProvider getAccesParRoles
     */
    public function testFiltreDemandesUrgentes(string $role)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service et on se connecte avec
        $service = self::getOneServiceFromRole($role);
        self::loginAs($client, $service);

        // On crée la demande et requête le tableau de bord
        $this->createDemande([EtatInterventionEnCours::class]);
        $client->request('GET', '/');

        // Si le service connecté est Amin ou Dme, le filtre Demandes urgentes est affiché, autrement non
        if (in_array($role, [Service::ROLE_ADMIN,Service::ROLE_DME])) {
            $this->assertSelectorExists('main .demande-urgente');
        } else {
            $this->assertSelectorNotExists('main .demande-urgente');
        }
    }

    /**
     * Test de présence de demande envoyée à SI2A et non fermée
     * (statut Intervention en cours, visible par tous les roles)
     * @dataProvider getAccesParRoles
     */
    public function testPresenceDeDemandeEnvoyeeEtNonFermee(string $role)
    {
        // Création du client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();

        // On récupère l'entity manager du client
        self::$entityManager = self::getEm($client);

        // On récupère le service et on se connecte avec
        $service = self::getOneServiceFromRole($role);
        self::loginAs($client, $service);

        // On crée la demande et requête le tableau de bord
        $this->createDemande([EtatInterventionEnCours::class]);
        $client->request('GET', '/');

        $this->assertSelectorTextContains('main .table', 'Intervention en cours');
    }

    public function getAccesParRoles(): array
    {
        return [
            Service::ROLE_INVITE => [
                Service::ROLE_INVITE
            ],
            Service::ROLE_ADMIN => [
                Service::ROLE_ADMIN
            ],
            Service::ROLE_DME => [
                Service::ROLE_DME
            ],
            Service::ROLE_INTERVENANT => [
                Service::ROLE_INTERVENANT
            ],

        ];
    }
}
