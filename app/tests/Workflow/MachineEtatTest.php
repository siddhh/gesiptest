<?php

namespace App\Tests\Workflow;

use App\Entity\References\Mission;
use App\Entity\References\MotifRefus;
use App\Entity\References\MotifRenvoi;
use App\Workflow\Actions\ActionEnvoyerRenvoie;
use App\Workflow\Actions\ActionInstruire;
use App\Workflow\Actions\ActionLancerConsultationCdb;
use App\Workflow\Etats\EtatDebut;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\MachineEtat;
use Faker;
use App\Tests\UserWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\Demande\Impact;
use App\Entity\References\MotifIntervention;
use App\Entity\References\NatureImpact;

use App\Workflow\Actions\ActionAccorder;
use App\Workflow\Actions\ActionAnnuler;
use App\Workflow\Actions\ActionDonnerAvis;
use App\Workflow\Actions\ActionDonnerAvisCdb;
use App\Workflow\Actions\ActionEnregistrer;
use App\Workflow\Actions\ActionEnvoyer;
use App\Workflow\Actions\ActionLancerConsultation;
use App\Workflow\Actions\ActionLancerInformation;
use App\Workflow\Actions\ActionRefuser;
use App\Workflow\Actions\ActionSaisirRealise;

use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;

class MachineEtatTest extends UserWebTestCase
{
    /** @var KernelBrowser $testClient */
    private $testClient;
    /** @var DemandeIntervention */
    private $testDemande;
    /** @var int */
    private $testNbHistorique;

    /**
     * Teste la récupération d'une machine à état
     */
    public function testRecuperationMachineAvecStatus()
    {
        // On récupère l'entity manager
        $em = static::getEm();

        // On crée un service random
        $service = new Service();
        $service->setLabel("Random");
        $service->setRoles(['ROLE_ADMIN']);

        // On crée une demande
        $demande = new DemandeIntervention();
        $demande->setStatus(EtatAnalyseEnCours::class);
        $machineEtat = $demande->getMachineEtat($service);

        // On test qu'on récupère bien notre machine à états
        $this->assertEquals(MachineEtat::class, get_class($machineEtat));
        $this->assertEquals(EtatAnalyseEnCours::class, get_class($machineEtat->getEtatActuel()));
    }

     /**
     * Génère une demande d'intervention en base de données pour effectuer des tests
     */
    public function creerDemande()
    {
        // On récupère notre Entity Manager dans notre contexte principal
        $em = static::getEm();

        // On initialise faker
        $faker = Faker\Factory::create('fr_FR');
        // Récupère des objets pour générer une demande d'intervention
        $motifs = $em->getRepository(MotifIntervention::class)->findAll();
        $natures = $em->getRepository(NatureImpact::class)->findAll();
        $composants = $em->getRepository(Composant::class)->findAll();
        $services = $em->getRepository(Service::class)->findAll();

        // On génère la demande
        $demande = new DemandeIntervention();
        $demande->genererNumero();
        $demande->setDemandePar($services[rand(0, count($services) - 1)]);
        $demande->setComposant($composants[rand(0, count($composants) - 1)]);
        $demande->setMotifIntervention($motifs[rand(0, count($motifs) - 1)]);
        $demande->setNatureIntervention(rand(0, 10) < 8 ? DemandeIntervention::NATURE_NORMAL : DemandeIntervention::NATURE_URGENT);
        $demande->setPalierApplicatif(rand(0, 1) > 0);
        $demande->setDescription($faker->text(256));
        $demande->setSolutionContournement($faker->optional(7, '')->text(128));
        $startIntervention = time() + rand(0, 864000);
        $minEndIntervention = $startIntervention + rand(0, 10000);
        $maxEndIntervention = $minEndIntervention + rand(0, 10000);
        $demande->setDateDebut(new \DateTime("@$startIntervention"));
        $demande->setDateFinMini(new \DateTime("@$minEndIntervention"));
        $demande->setDateFinMax(new \DateTime("@$maxEndIntervention"));
        $demande->setDureeRetourArriere(rand(0, 200));
        $demande->setStatus(EtatDebut::class);
        for ($j = 0; $j < rand(1, 5); $j++) {
            $startImpact = $startIntervention + rand(0, 10000);
            $minEndImpact = $startImpact + rand(0, 10000);
            $maxEndImpact = $minEndImpact + rand(0, 1000);
            $impact = new Impact();
            $impact->setNumeroOrdre($j + 1);
            $impact->setNature($natures[rand(0, count($natures) - 1)]);
            $impact->setCertitude(rand(0, 1) > 0);
            $impact->setCommentaire($faker->optional(7, '')->text(64));
            if ($impact->getNature()->getId() !== 1) {
                $impact->setDateDebut(new \DateTime("@$startImpact"));
                $impact->setDateFinMini(new \DateTime("@$minEndImpact"));
                $impact->setDateFinMax(new \DateTime("@$maxEndImpact"));
            }
            for ($k = 0; $k < rand(0, 5); $k++) {
                $impact->addComposant($composants[rand(0, count($composants) - 1)]);
            }
            $demande->addImpact($impact);
            $em->persist($impact);
        }
        $em->persist($demande);
        $em->flush();
        return $demande;
    }

    /**
     * On ajoute le service passé en paramètre en tant qu'exploitant du composant et service effectuant l'intervention.
     *
     * @param Service             $service
     */
    private function ajoutServiceExploitant(Service $service): void
    {
        // On va récupérer une mission random
        $mission = $this->getRandom(static::$entityManager->getRepository(Mission::class)->findAll());

        // On crée l'annuaire qui va bien
        $annuaire = new Annuaire();
        $annuaire->setService($service);
        $annuaire->setComposant($this->testDemande->getComposant());
        $annuaire->setMission($mission);
        static::$entityManager->persist($annuaire);

        // On ajoute l'annuaire crée dans la demande
        $this->testDemande->addService($annuaire);

        // On tire la chasse !
        static::$entityManager->flush();
    }

    /**
     * Permet de tester le code de status, le nom de la classe, le nombre d'élément dans l'historique.
     *
     * @param int|null            $attenduHttpCode
     * @param string|null         $attenduStatusClass
     * @param int|null            $attenduNbHistoriques
     */
    private function assertResultatAction(?int $attenduHttpCode = null, ?string $attenduStatusClass = null, ?int $attenduNbHistoriques = null)
    {
        self::$entityManager = static::getEm($this->testClient);
        $this->testDemande = self::$entityManager->getRepository(DemandeIntervention::class)->find($this->testDemande->getId());
        self::$entityManager->refresh($this->testDemande);

        if ($attenduHttpCode !== null) {
            $clientResponse = $this->testClient->getResponse();
            $this->assertEquals(
                $attenduHttpCode,
                $clientResponse->getStatusCode(),
                $clientResponse->getStatusCode() . ' => ' . $clientResponse->getContent()
            );
        }

        if ($attenduStatusClass !== null) {
            $this->assertEquals($attenduStatusClass, $this->testDemande->getStatus());
        }

        if ($attenduNbHistoriques !== null) {
            $this->assertEquals($attenduNbHistoriques, $this->testDemande->getHistoriqueStatus()->count());
        }
    }

    /**
     * Fonction permettant d'initialiser les tests.
     */
    private function initialisationTestsScenarios()
    {
        // On crée notre client
        $this->testClient = static::createClient();
        // On récupère l'Entity Manager
        self::$entityManager = static::getEm($this->testClient);
        // On récupère le kernel du client
        global $kernel;
        $kernel = $this->testClient->getKernel();
        // On génère la demande d'intervention que l'on utilisera par la suite
        $this->testDemande = $this->creerDemande();
        // On initialise le nombre d'élément de l'historique à tester
        $this->testNbHistorique = 0;
    }

    /**
     * Fonction permettant de récupérer de manière aléatoire une entrée parmi une collection passée en paramètre
     * Si le second paramètre est à true, alors, nous pouvons également avoir la valeur null (1/3 des cas)
     * @param $collection
     * @param bool $nullable
     * @return mixed
     */
    private function getRandom($collection, $nullable = false)
    {
        if ($nullable && rand(0, 2) === 0) {
            return null;
        } else {
            $randomIndex = rand(0, (count($collection) - 1));
            return $collection[$randomIndex];
        }
    }

    /**
     * Fontion permettant de récupérer l'url de l'action en fonction d'une demande passé en paramètre.
     *
     * @param DemandeIntervention $demande
     * @return string
     */
    private function getUrlAction(DemandeIntervention $demande) : string
    {
        return sprintf(
            '/ajax/demandes/%d/action',
            $demande->getId()
        );
    }

    /**
     * Fonction permettant de récupérer un service admin à jour!
     * @return Service
     */
    private function getServiceAdmin() : Service
    {
        return self::getEmRepository(Service::class)->findOneBy(['label' => '0 Service Administrateur']);
    }

    /**
     * Fonction permettant de récupérer un service intervenant à jour!
     * @return Service
     */
    private function getServiceIntervenant() : Service
    {
        return self::getEmRepository(Service::class)->findOneBy(['label' => '0 Service Intervenant']);
    }

    /**
     * On connecte notre test client en tant que $service.
     *
     * @param Service $service
     */
    private function connexion(Service $service) : void
    {
        self::loginAs($this->testClient, $service);
    }

    /**
     * Action enregistrer
     */
    private function actionEnregistrer() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [ 'action' => ActionEnregistrer::class ]);
        $this->assertResultatAction(200, EtatBrouillon::class, $this->testNbHistorique);
    }

    /**
     * Action envoyer
     */
    private function actionEnvoyer() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [ 'action' => ActionEnvoyer::class ]);
        $this->assertResultatAction(200, EtatAnalyseEnCours::class, $this->testNbHistorique);
    }

    /**
     * Action annuler
     */
    private function actionAnnuler() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionAnnuler::class,
            'commentaire' => 'Ceci est un commentaire d\'annulation.'
        ]);
        $this->assertResultatAction(200, EtatAnnulee::class, $this->testNbHistorique);
        $this->assertEquals([
            'commentaire' => 'Ceci est un commentaire d\'annulation.'
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
    }

    /**
     * Action refuser
     */
    private function actionRefuser() : void
    {
        $this->testNbHistorique++;
        $refusMotif = $this->getRandom(self::$entityManager->getRepository(MotifRefus::class)->findAll());
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionRefuser::class,
            'commentaire' => 'Ceci est un commentaire de refus.',
            'motif' => $refusMotif->getId()
        ]);
        $this->assertResultatAction(200, EtatRefusee::class, $this->testNbHistorique);
        $this->assertEquals([
            'commentaire' => 'Ceci est un commentaire de refus.',
            'motif' => [
                'id' => $refusMotif->getId(),
                'label' => $refusMotif->getLabel(),
            ]
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
    }

    /**
     * Action lancer l'information
     */
    private function actionLancerInformation() : void
    {
        $this->testNbHistorique++;
        $annuaire = $this->getRandom(self::$entityManager->createQueryBuilder()
            ->select('a')
            ->from(Annuaire::class, 'a')
            ->join('a.mission', 'm')
            ->join('a.service', 's')
            ->where('a.composant = :composant')
            ->setParameter('composant', $this->testDemande->getComposant())
            ->orderBy('s.label', 'ASC')
            ->getQuery()
            ->getResult());
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionLancerInformation::class,
            'annuaires' => [
                'ids' => [
                    $annuaire->getId(),
                ]
            ]
        ]);
        $this->assertResultatAction(200, EtatInstruite::class, $this->testNbHistorique);
        $this->assertEquals([
            'annuaires' => [
                $annuaire->getId()
            ]
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
    }

    /**
     * Action accorder
     */
    private function actionAccorder() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionAccorder::class,
            'commentaire' => 'Ceci est un commentaire d\'accord.'
        ]);
        $this->assertResultatAction(200, EtatAccordee::class, $this->testNbHistorique);
        $this->assertEquals([
            'commentaire' => 'Ceci est un commentaire d\'accord.'
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
    }

    /**
     * Action Démarrer l'intervention (par CRON normalement)
     */
    private function actionDemarrerIntervention() : void
    {
        $this->testNbHistorique++;
        $dateDebutIntervention = (new \DateTime())->sub(new \DateInterval('P2D'));
        $this->testDemande->setDateDebut($dateDebutIntervention);
        $this->testDemande->getMachineEtat()->changerEtat(EtatInterventionEnCours::class);
        self::$entityManager->flush();
        $this->assertResultatAction(null, EtatInterventionEnCours::class, $this->testNbHistorique);
    }

    /**
     * Action Terminer l'intervention (par CRON normalement)
     */
    private function actionTerminerIntervention() : void
    {
        $this->testNbHistorique++;
        $dateDebutIntervention = (new \DateTime())->sub(new \DateInterval('P1D'));
        $this->testDemande->setDateFinMini($dateDebutIntervention);
        $this->testDemande->setDateFinMax($dateDebutIntervention);
        $this->testDemande->getMachineEtat()->changerEtat(EtatSaisirRealise::class);
        self::$entityManager->flush();
        $this->assertResultatAction(null, EtatSaisirRealise::class, $this->testNbHistorique);
    }

    /**
     * Action Saisir le réalisé
     *
     * @param string              $avis
     * @param string              $etatStatutAttendu
     */
    private function actionSaisirRealise(string $avis, string $etatStatutAttendu) : void
    {
        if ($etatStatutAttendu !== EtatSaisirRealise::class) {
            $this->testNbHistorique++;
        }
        $dateDebutIntervention = new \DateTime("@" . (time() - (24 * 60)));
        $nature = $this->getRandom(static::$entityManager->getRepository(NatureImpact::class)->findAll());
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionSaisirRealise::class,
            'resultat' => $avis,
            'impactReels' => [
                [
                    'dateDebut' => $dateDebutIntervention->format('d/m/Y 00:00'),
                    'dateFin' => $dateDebutIntervention->format('d/m/Y 00:00'),
                    'nature' => $nature->getId(),
                    'composants' => [],
                    'commentaire' => 'Ceci est un commentaire d`un impact réel 1.'
                ],
                [
                    'dateDebut' => $dateDebutIntervention->format('d/m/Y 00:00'),
                    'dateFin' => $dateDebutIntervention->format('d/m/Y 00:00'),
                    'nature' => $nature->getId(),
                    'composants' => [],
                    'commentaire' => 'Ceci est un commentaire d`un impact réel 2.'
                ]
            ],
            'commentaire' => 'Ceci est un commentaire de saisie de réalisé global.',
        ]);
        $this->assertResultatAction(200, $etatStatutAttendu, $this->testNbHistorique);
        $derniereSaisie = $this->testDemande->getSaisieRealises()->last();
        $this->assertEquals($avis, $derniereSaisie->getResultat());
        $this->assertEquals('Ceci est un commentaire de saisie de réalisé global.', $derniereSaisie->getCommentaire());
        $this->assertEquals(2, $derniereSaisie->getImpactReels()->count());
    }

    /**
     * Action Renvoyer
     */
    private function actionRenvoyer() : void
    {
        $this->testNbHistorique++;
        $motif = $this->getRandom(static::getEmRepository(MotifRenvoi::class)->findAll());
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => 'ActionRenvoyer',
            'motifs' => [
                [
                    'motif'       => $motif->getId(),
                    'commentaire' => 'Ceci est un commentaire de renvoi.'
                ]
            ]

        ]);
        $this->assertResultatAction(200, EtatRenvoyee::class, $this->testNbHistorique);
        $this->assertEquals([
            [
                'motif'       => [
                    'id'    => $motif->getId(),
                    'label' => $motif->getLabel(),
                ],
                'commentaire' => 'Ceci est un commentaire de renvoi.'
            ]
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
    }

    /**
     * Action Envoyer après renvoi (envoyer une correction)
     */
    private function actionEnvoyerApresRenvoi() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), ['action' => ActionEnvoyerRenvoie::class]);
        $this->assertResultatAction(200, EtatAnalyseEnCours::class, $this->testNbHistorique);
    }

    /**
     * Action Lancer consultation
     *  En retour la méthode renvoie l'annuaire qui contient le service consulté
     * @return Annuaire
     */
    private function actionLancerConsultation($avecConsultationCbd = false) : Annuaire
    {
        $this->testNbHistorique++;
        $composantIds = [$this->testDemande->getComposant()->getId()];
        foreach ($this->testDemande->getImpacts() as $impact) {
            foreach ($impact->getComposants() as $composant) {
                $composantIds[] = $composant->getId();
            }
        }
        $annuaire = $this->getRandom(self::$entityManager->createQueryBuilder()
            ->select('a')
            ->from(Annuaire::class, 'a')
            ->join('a.mission', 'm')
            ->join('a.service', 's')
            ->where('a.composant IN (:composantIds)')
            ->setParameter('composantIds', $composantIds)
            ->orderBy('s.label', 'ASC')
            ->getQuery()
            ->getResult());
        // Génère une date limite valide
        $tsDateLimiteMin = time();
        $tsDateLimiteMax = (clone $this->testDemande->getDateLimiteDecisionDme())->getTimestamp();
        $dateLimite = (new \DateTime())->setTimestamp(rand($tsDateLimiteMin, $tsDateLimiteMax));
        $dateLimiteString = $dateLimite->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y');
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action'    => ActionLancerConsultation::class,
            'dateLimite' => $dateLimiteString,
            'avecCdb' => $avecConsultationCbd,
            'annuaires' => [
                'ids' => [
                    $annuaire->getId(),
                ]
            ]
        ]);
        $this->assertResultatAction(200, EtatConsultationEnCours::class, $this->testNbHistorique);
        $this->assertEquals([
            'annuaires' => [
                $annuaire->getId()
            ],
            'dateLimite' => $dateLimiteString,
            'avecCdb' => $avecConsultationCbd
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
        return $annuaire;
    }

    /**
     * Action Instruire
     */
    private function actionInstruire() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionInstruire::class,
        ]);
        $this->assertResultatAction(200, EtatInstruite::class, $this->testNbHistorique);
    }

    /**
     * Action Donner avis
     *
     * @param string $avis
     */
    private function actionDonnerAvis(string $avis) : void
    {
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionDonnerAvis::class,
            'avis' => $avis,
            'commentaire' => 'Ceci est mon avis.'
        ]);
        $this->assertResultatAction(200, EtatConsultationEnCours::class, $this->testNbHistorique);
    }

    /**
     * Action Lancer consultation du Cdb
     */
    private function actionLancerConsultationCdb() : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionLancerConsultationCdb::class,
            'commentaire' => 'Ceci est une commentaire de consultation.'
        ]);
        $this->assertResultatAction(200, EtatConsultationEnCoursCdb::class, $this->testNbHistorique);
        $this->assertEquals([
            'commentaire' => 'Ceci est une commentaire de consultation.'
        ], $this->testDemande->getHistoriqueStatus()->first()->getDonnees());
    }

    /**
     * Action Donner avis du Cdb
     *
     * @param string $avis
     */
    private function actionDonnerAvisCdb(string $avis) : void
    {
        $this->testNbHistorique++;
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionDonnerAvisCdb::class,
            'avis' =>  $avis,
            'commentaire' => 'Ceci est l\'avis du CDB.'
        ]);
        $this->assertResultatAction(200, EtatInstruite::class, $this->testNbHistorique);
        $donneesHistorique = $this->testDemande->getHistoriqueStatus()->get(1)->getDonnees();
        unset($donneesHistorique['CDB']['date']);
        $this->assertEquals([
            'commentaire' => 'Ceci est une commentaire de consultation.',
            'CDB' => [
                'serviceId' => $this->getServiceAdmin()->getId(),
                'avis' => $avis,
                'commentaire' => 'Ceci est l\'avis du CDB.'
            ]
        ], $donneesHistorique);
    }

    /**
     * Teste la machine à état avec le scénario 1 'Demande Annulée' le plus simple
     * - Enregistrer
     * - Envoyer
     * - Annuler
     */
    public function testDemandeAnnuleeSimple()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Annuler
        $this->actionAnnuler();
    }

    /**
     * Teste la machine à état avec le scénario 4 'Demande Renvoyée' le plus simple
     * - Enregistrer
     * - Envoyer
     * - Refuser
     */
    public function testDemandeRenvoyeeSimple()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Refuser
        $this->actionRefuser();
    }

    /**
     * Teste la machine à état avec le scénario 7 'Demande Terminée' le plus simple
     * - Enregistrer
     * - Envoyer
     * - Lancer l'information
     * - Accorder
     * (- Début de l'intervention atteinte)
     * (- Fin de l'intervention atteinte)
     * - Saisir le réalisé par un admin
     * - Saisir le réalisé par un intervenant
     * - Intervention réussie (car intervenant ok)
     */
    public function testDemandeTermineeSimple()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();
        $serviceIntervenant = $this->getServiceIntervenant();

        // On ajoute les services qui interviennent sur la demande
        $this->ajoutServiceExploitant($serviceIntervenant);

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Lancer l'information
        $this->actionLancerInformation();
        // Accorder
        $this->actionAccorder();
        // Début de l'intervention
        $this->actionDemarrerIntervention();
        // Fin de l'intervention
        $this->actionTerminerIntervention();
        // (Connexion intervenant)
        $this->connexion($serviceIntervenant);
        // Saisir le réalisé par un service intervenant
        $this->actionSaisirRealise('ok', EtatInterventionReussie::class);
    }

    /**
     * Teste la machine à état avec le scénario 2 'Demande Annulée' médian: avec un renvoi
     * - Enregistrer
     * - Envoyer
     * - Renvoyer
     * - Envoyer la correction
     * - Lancer l'information
     * - Annuler
     */
    public function testDemandeAnnuleeAvecRenvoi()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Renvoyer
        $this->actionRenvoyer();
        // Envoyer la correction
        $this->actionEnvoyerApresRenvoi();
        // Lancer l'information
        $this->actionLancerInformation();
        // Annuler
        $this->actionAnnuler();
    }

    /**
     * Teste la machine à état avec le scénario 5 'Demande Renvoyée' médian: avec un renvoi
     * - Enregistrer
     * - Envoyer
     * - Renvoyer
     * - Envoyer la correction
     * - Lancer l'information
     * - Refuser
     */
    public function testDemandeRenvoyeeAvecRenvoi()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Renvoyer
        $this->actionRenvoyer();
        // Envoyer la correction
        $this->actionEnvoyerApresRenvoi();
        // Lancer l'information
        $this->actionLancerInformation();
        // Refuser
        $this->actionRefuser();
    }

    /**
     * Teste la machine à état avec le scénario 8 'Demande Terminée' médian: avec un renvoi
     * - Enregistrer
     * - Envoyer
     * - Renvoyer
     * - Envoyer la correction
     * - Lancer la consultation
     * - Instruire la demande
     * - Accorder
     * - Début de l'intervention atteinte
     * - Fin de l'intervention atteinte
     * - Saisir le réalisé
     */
    public function testDemandeTermineeAvecRenvoi()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();
        $serviceIntervenant = $this->getServiceIntervenant();

        // On ajoute les services qui interviennent sur la demande
        $this->ajoutServiceExploitant($serviceIntervenant);

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Renvoyer
        $this->actionRenvoyer();
        // Envoyer la correction
        $this->actionEnvoyerApresRenvoi();
        // Lancer la consultation
        $this->actionLancerConsultation();
        // Instruire la demande
        $this->actionInstruire();
        // Accorder
        $this->actionAccorder();
        // Début de l'intervention atteinte
        $this->actionDemarrerIntervention();
        // Fin de l'intervention atteinte
        $this->actionTerminerIntervention();
        // (connexion intervenant)
        $this->connexion($serviceIntervenant);
        // Saisir le réalisé
        $this->actionSaisirRealise('ko', EtatInterventionEchouee::class);
    }

    /**
     * Teste la machine à état avec le scénario 3 'Demande Annulée' complexe
     * - Enregistrer
     * - Envoyer
     * - Lancer la consultation
     * - Donner un avis
     * - Consulter le Cdb
     * - Donner un avis Cdb
     * - Accorder
     * - Annuler
     */
    public function testDemandeAnnuleeComplexe()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();
        $serviceIntervenant = $this->getServiceIntervenant();

        // On ajoute les services qui interviennent sur la demande
        $this->ajoutServiceExploitant($serviceIntervenant);

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Lancer la consultation
        $annuaireConsulte = $this->actionLancerConsultation(true);
        // (Connexion intervenant consulté)
        $this->connexion($annuaireConsulte->getService());
        // Donner un avis en tant qu'intervenant
        $this->actionDonnerAvis('ok');
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Consulter le CDB
        $this->actionLancerConsultationCdb();
        // Donner un avis CDB
        $this->actionDonnerAvisCdb('ok');
        // Accorder
        $this->actionAccorder();
        // Annuler
        $this->actionAnnuler();
    }

    /**
     * Teste la machine à état avec le scénario 6 'Demande Renvoyée' complexe
     * - Enregistrer
     * - Envoyer
     * - Lancer l'information
     * - Renvoyer
     * - Envoyer la correction
     * - Lancer la consultation
     * - Instruire la demande
     * - Accorder
     * - Refuser
     */
    public function testDemandeRenvoyeeComplexe()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();
        $serviceIntervenant = $this->getServiceIntervenant();

        // On ajoute les services qui interviennent sur la demande
        $this->ajoutServiceExploitant($serviceIntervenant);

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Lancer l'information
        $this->actionLancerInformation();
        // Renvoyer
        $this->actionRenvoyer();
        // Envoyer la correction
        $this->actionEnvoyerApresRenvoi();
        // Lancer la consultation
        $this->actionLancerConsultation();
        // Instruire
        $this->actionInstruire();
        // Accorder
        $this->actionAccorder();
    }

    /**
     * Teste la machine à état avec le scénario 9 'Demande Terminée' complexe
     * - Enregistrer
     * - Envoyer
     * - Lancer la consultation
     * - Donner un avis
     * - Consulter le Cdb
     * - Donner un avis
     * - Renvoyer
     * - Envoyer la correction
     * - Lancer la consultation
     * - Donner un avis
     * - Instruire la demande
     * - Accorder
     * - Renvoyer
     * - Envoyer la correction
     * - Lancer l'information
     * - Accorder
     * - Début de l'intervention atteinte
     * - Fin de l'intervention atteinte
     * - Saisir le réalisé
     */
    public function testDemandeTermineeComplexe()
    {
        // On initialise le nécessaire pour faire tourner les tests
        $this->initialisationTestsScenarios();
        $serviceAdmin = $this->getServiceAdmin();
        $serviceIntervenant = $this->getServiceIntervenant();

        // On ajoute les services qui interviennent sur la demande
        $this->ajoutServiceExploitant($serviceIntervenant);

        // --- On effectue notre scénario
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Enregistrer
        $this->actionEnregistrer();
        // Envoyer
        $this->actionEnvoyer();
        // Lancer la consultation
        $annuaireConsulte = $this->actionLancerConsultation(true);
        // (Connexion intervenant consulté)
        $this->connexion($annuaireConsulte->getService());
        // Donner un avis en tant qu'intervenant
        $this->actionDonnerAvis('ok');
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Consulter le CDB
        $this->actionLancerConsultationCdb();
        // Donner un avis CDB
        $this->actionDonnerAvisCdb('ok');
        // Renvoyer
        $this->actionRenvoyer();
        // Envoyer la correction
        $this->actionEnvoyerApresRenvoi();
        // Lancer la consultation
        $annuaireConsulte = $this->actionLancerConsultation();
        // (Connexion intervenant consulté)
        $this->connexion($annuaireConsulte->getService());
        // Donner un avis en tant qu'intervenant
        $this->actionDonnerAvis('ok');
        // (Connexion admin)
        $this->connexion($serviceAdmin);
        // Instruire la demande
        $this->actionInstruire();
        // Accorder
        $this->actionAccorder();
        // Renvoyer
        $this->actionRenvoyer();
        // Envoyer la correction
        $this->actionEnvoyerApresRenvoi();
        // Lancer l'information
        $this->actionLancerInformation();
        // Accorder
        $this->actionAccorder();
        // Début de l'intervention atteinte
        $this->actionDemarrerIntervention();
        // Fin de l'intervention atteinte
        $this->actionTerminerIntervention();
        // (Connexion intervenant)
        $this->connexion($serviceIntervenant);
        // Saisir le réalisé
        $this->actionSaisirRealise('ko', EtatInterventionEchouee::class);
    }
}
