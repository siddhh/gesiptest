<?php

namespace App\Tests\Workflow;

use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\DemandeIntervention;
use App\Entity\Demande\Impact;
use App\Entity\References\MotifIntervention;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Actions\ActionEnvoyer;
use App\Workflow\Actions\ActionDonnerAvis;
use App\Workflow\Actions\ActionDonnerAvisCdb;
use App\Workflow\Actions\ActionLancerConsultation;
use App\Workflow\Actions\ActionLancerConsultationCdb;
use App\Workflow\Actions\ActionLancerInformation;
use App\Workflow\Actions\ActionInstruire;
use App\Workflow\Actions\ActionAccorder;
use App\Workflow\Etats\EtatDebut;
use Faker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class DestinatairesCollectionTest extends UserWebTestCase
{
    /** @var KernelBrowser $testClient */
    private $testClient;
    /** @var DemandeIntervention */
    private $testDemande;
    /** @var array */
    private $adresses = [];

    /**
     * Génère une demande d'intervention en base de données pour effectuer des tests
     */
    private function creerDemande(): DemandeIntervention
    {
        // On récupère notre Entity Manager dans notre contexte principal
        $em = static::getEm();

        // Récupère des objets pour générer une demande d'intervention
        $faker = Faker\Factory::create('fr_FR');
        $services = $em->getRepository(Service::class)->findAll();
        $composants = $em->getRepository(Composant::class)->findAll();
        $motifs = $em->getRepository(MotifIntervention::class)->findAll();
        $natures = $em->getRepository(NatureImpact::class)->findAll();

        // Récupère le service administrateur
        $adminService = $em->getRepository(Service::class)->findOneBy(['label' => '0 Service Administrateur']);

        // Initialise le tableau des adresses attendues (services ADMINS, DME et SI2A,..)
        $this->adresses = [
            'admins' => [
                $adminService->getEmail()   => new Address($adminService->getEmail(), $adminService->getLabel())
            ],
            'dmes' => [
                'gesip-dme@dgfip.local'     => Address::fromString('0 Service DME <gesip-dme@dgfip.local>')
            ],
            'si2a' => [
                'christine.gravosqui@dgfip.finances.gouv.fr'        => Address::fromString('Christine GRAVOSQUI <christine.gravosqui@dgfip.finances.gouv.fr>'),
                'jean-francois.guilbert@dgfip.finances.gouv.fr'     => Address::fromString('Jean-François GUILBERT <jean-francois.guilbert@dgfip.finances.gouv.fr>'),
                'laurent-l.fraisse@dgfip.finances.gouv.fr'          => Address::fromString('Laurent FRAISSE <laurent-l.fraisse@dgfip.finances.gouv.fr>'),
                'jean-christophe.pommier@dgfip.finances.gouv.fr'    => Address::fromString('Jean-Cristophe POMMIER <jean-christophe.pommier@dgfip.finances.gouv.fr>'),
                'claude.gatti@dgfip.finances.gouv.fr'               => Address::fromString('Claude GATTI <claude.gatti@dgfip.finances.gouv.fr>'),
                'eric.rebouillet-petiot@dgfip.finances.gouv.fr'     => Address::fromString('Eric REBOUILLET-PETIOT <eric.rebouillet-petiot@dgfip.finances.gouv.fr>'),
                'marie-pierre.ligout@dgfip.finances.gouv.fr'        => Address::fromString('Marie-Pierre LIGOUT <marie-pierre.ligout@dgfip.finances.gouv.fr>'),
            ],
            'demandeur' => [],
            'intervenants' => [],
            'composant' => [],
            'piloteequipedmes' => [],
            'impactes' => [],
            'consultes' => [],
        ];

        // On génère la demande
        $demande = new DemandeIntervention();
        $demande->genererNumero();
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

        // Définition du service demandeur
        $serviceDemandeur = $services[rand(0, count($services) - 1)];
        $this->adresses['demandeur'][$serviceDemandeur->getEmail()] = new Address($serviceDemandeur->getEmail(), $serviceDemandeur->getLabel());
        $demande->setDemandePar($serviceDemandeur);

        // Définition du composant et des intervenants
        $composant = $composants[rand(0, count($composants) - 1)];
        $demande->setComposant($composant);
        foreach ($composant->getAnnuaire() as $annuaire) {
            $annuaireLabel = $annuaire->getService()->getLabel() . ' (' . $annuaire->getMission()->getLabel() . ')';
            $this->adresses['composant'][$annuaire->getBalf()] = new Address($annuaire->getBalf(), $annuaireLabel);
            if (rand(0, 1) > 0) {
                $demande->addService($annuaire);
                $this->adresses['intervenants'][$annuaire->getBalf()] = new Address($annuaire->getBalf(), $annuaireLabel);
            }
        }

        // Ajout des exploitants extérieurs
        for ($i = 0; $i < rand(0, 3); $i++) {
            $service = $services[rand(0, count($services) - 1)];
            $demande->addExploitantExterieur($service);
            $this->adresses['intervenants'][$service->getEmail()] = new Address($service->getEmail(), $service->getLabel());
        }

        // Définition du groupe de destinataires piloteequipedmes
        $this->adresses['piloteequipedmes'] = [];
        $pilote = $composant->getPilote();
        $piloteSuppleant = $composant->getPiloteSuppleant();
        $equipe = $composant->getEquipe();
        if (null !== $pilote) {
            $this->adresses['piloteequipedmes'][$pilote->getBalp()] = new Address($pilote->getBalp(), $pilote->getNomCompletCourt());
        }
        if (null !== $piloteSuppleant) {
            $this->adresses['piloteequipedmes'][$piloteSuppleant->getBalp()] = new Address($piloteSuppleant->getBalp(), $piloteSuppleant->getNomCompletCourt());
        }
        if (null !== $equipe) {
            $this->adresses['piloteequipedmes'][$equipe->getEmail()] = new Address($equipe->getEmail(), $equipe->getLabel());
        } else {
            $this->adresses['piloteequipedmes'] = array_merge($this->adresses['piloteequipedmes'], $this->adresses['dmes']);
        }

        // Définition des impacts
        $this->adresses['impactes'] = [];
        for ($i = 0; $i < rand(0, 3); $i++) {
            $impact = new Impact();
            $impact->setNumeroOrdre($i + 1);
            $impact->setNature($natures[rand(0, count($natures) - 1)]);
            $impact->setCertitude(rand(0, 1));
            $impact->setCommentaire($faker->text(256));
            for ($j = 0; $j < rand(1, 3); $j++) {
                $composant = $composants[rand(0, count($composants) - 1)];
                $impact->addComposant($composant);
                foreach ($composant->getAnnuaire() as $annuaire) {
                    $annuaireLabel = $annuaire->getService()->getLabel() . ' (' . $annuaire->getMission()->getLabel() . ')';
                    $this->adresses['impactes'][$annuaire->getBalf()] = new Address($annuaire->getBalf(), $annuaireLabel);
                }
                if (($composant->getEstSiteHebergement()) && ($composant->getId() != $demande->getComposant()->getId())) {
                    foreach ($composant->getFluxSortants(false) as $composantHebergement) {
                        foreach ($composantHebergement->getAnnuaire() as $annuaireHebergement) {
                            $annuaireLabel = $annuaireHebergement->getService()->getLabel() . ' (' . $annuaireHebergement->getMission()->getLabel() . ')';
                            $this->adresses['impactes'][$annuaireHebergement->getBalf()] = new Address($annuaireHebergement->getBalf(), $annuaireLabel);
                        }
                    }
                }
            }
            $em->persist($impact);
            $demande->addImpact($impact);
        }

        // Enregistrement et renvoi de la demande
        $em->persist($demande);
        $em->flush();

        return $demande;
    }

    /**
     * Fonction permettant d'initialiser les tests.
     */
    private function initialisationTests(array $scenario = [])
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
        // Fait "vivre" la demande avec le scénario voulu
        foreach ($scenario as $action) {
            switch ($action) {
                case 'connexion|admin':
                    $adminService = static::getEm()->getRepository(Service::class)->findOneBy(['label' => '0 Service Administrateur']);
                    $this->connexion($adminService);
                    break;
                case ActionEnvoyer::class:
                    $this->actionEnvoyer();
                    break;
                case ActionLancerInformation::class:
                    $this->actionLancerInformation();
                    break;
                case ActionLancerConsultation::class:
                    $this->actionLancerConsultation();
                    break;
                case ActionLancerConsultationCdb::class:
                    $this->actionLancerConsultationCdb();
                    break;
                case ActionDonnerAvis::class:
                    $this->actionDonnerAvis(rand(0, 1) ? 'ok' : 'ko');
                    break;
                case ActionDonnerAvisCdb::class:
                    $this->actionDonnerAvisCdb();
                    break;
                case ActionInstruire::class:
                    $this->actionInstruire();
                    break;
                case ActionAccorder::class:
                    $this->actionAccorder();
                    break;
                default:
                    throw new \Exception("Action {$action} de scénario inconnue.");
            }
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
     * Fonction permettant de retourner l'intégralité ou partie (y compris aucun) des elements contenus dans $collection
     * @param iterable $collection
     * @return mixed
     */
    private function getRandomIterable(iterable $collection)
    {
        $ret = [];
        for ($i = 0; $i < rand(0, count($collection) - 1); $i++) {
            $ret[] = $collection[rand(0, count($collection) - 1)];
        }
        return $ret;
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
     * Action envoyer
     */
    private function actionEnvoyer() : void
    {
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionEnvoyer::class
        ]);
    }

    /**
     * Action lancer l'information
     */
    private function actionLancerInformation() : void
    {
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

        $this->adresses['consultes'] = [];
        $annuaireLabel = $annuaire->getService()->getLabel() . ' (' . $annuaire->getMission()->getLabel() . ')';
        $this->adresses['consultes'][$annuaire->getBalf()] = new Address($annuaire->getBalf(), $annuaireLabel);

        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionLancerInformation::class,
            'annuaires' => [
                'ids' => [ $annuaire->getId() ]
            ]
        ]);
    }

    /**
     * Action Lancer consultation
     */
    private function actionLancerConsultation()
    {
        $composantIds = [$this->testDemande->getComposant()->getId()];
        foreach ($this->testDemande->getImpacts() as $impact) {
            foreach ($impact->getComposants() as $composant) {
                $composantIds[] = $composant->getId();
            }
        }
        $annuaires = $this->getRandomIterable(
            self::$entityManager->createQueryBuilder()
                ->select('a')
                ->from(Annuaire::class, 'a')
                ->join('a.mission', 'm')
                ->join('a.service', 's')
                ->where('a.composant IN (:composantIds)')
                ->setParameter('composantIds', $composantIds)
                ->orderBy('s.label', 'ASC')
                ->getQuery()
                ->getResult()
        );
        $annuairesIds = [];
        $this->adresses['consultes'] = [];
        foreach ($annuaires as $annuaire) {
            $annuairesIds[] = $annuaire->getId();
            $annuaireLabel = $annuaire->getService()->getLabel() . ' (' . $annuaire->getMission()->getLabel() . ')';
            $this->adresses['consultes'][$annuaire->getBalf()] = new Address($annuaire->getBalf(), $annuaireLabel);
        }
        $tsDateLimiteMin = time();
        $tsDateLimiteMax = (clone $this->testDemande->getDateLimiteDecisionDme())->getTimestamp();
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action'        => ActionLancerConsultation::class,
            'dateLimite'    => (new \DateTime())->setTimestamp(rand($tsDateLimiteMin, $tsDateLimiteMax))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y'),
            'annuaires'     => [
                'ids' => $annuairesIds
            ]
        ]);
    }

    /**
     * Action Donner avis
     *
     * @param string $avis
     */
    private function actionDonnerAvis(string $avis) : void
    {
        $faker = Faker\Factory::create('fr_FR');
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action'        => ActionDonnerAvis::class,
            'avis'          => $avis,
            'commentaire'   => $faker->text(128),
        ]);
    }

    /**
     * Action Lancer consultation du Cdb
     */
    private function actionLancerConsultationCdb() : void
    {
        $faker = Faker\Factory::create('fr_FR');
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionLancerConsultationCdb::class,
            'commentaire'   => $faker->text(128),
        ]);
    }

    /**
     * Action Donner avis du Cdb
     *
     * @param string $avis
     */
    private function actionDonnerAvisCdb(string $avis) : void
    {
        $faker = Faker\Factory::create('fr_FR');
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action'        => ActionDonnerAvisCdb::class,
            'avis'          => $avis,
            'commentaire'   => $faker->text(128),
        ]);
    }

    /**
     * Action Instruire
     */
    private function actionInstruire() : void
    {
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action' => ActionInstruire::class,
        ]);
    }

    /**
     * Action accorder
     */
    private function actionAccorder() : void
    {
        $faker = Faker\Factory::create('fr_FR');
        $this->testClient->request(Request::METHOD_POST, $this->getUrlAction($this->testDemande), [
            'action'        => ActionAccorder::class,
            'commentaire'   => $faker->text(128),
        ]);
    }

    /**
     * Vérifie qu'un tableau de destinataires est équivalent à celui attendu
     * @param array $aVerifierDestinataires
     * @param array $attenduDestinataires
     * @return bool
     */
    private function verifieDestinataires(array $aVerifierDestinataires, array $attenduDestinataires): bool
    {
        // Si le nombre d'élements est différent inutile de continuer
        if (count($aVerifierDestinataires) !== count($attenduDestinataires)) {
            $debugMessage = print_r(['a verifier' => $aVerifierDestinataires, 'attendu' => $attenduDestinataires], true);
            throw new \Exception("Nombre de destinataires attendus différents.\r\n{$debugMessage}");
        }

        // Pour chaque destinataire à vérifier
        foreach ($aVerifierDestinataires as $aVerifierAddress) {
            // Vérifie le type
            if (!$aVerifierAddress instanceof Address) {
                $debugMessage = print_r(['a verifier' => $aVerifierDestinataires, 'attendu' => $attenduDestinataires], true);
                throw new \Exception("Un destinataire n'est pas de type Address.\r\n{$debugMessage}");
            }
            // Vérifie si le destinataire à vérifier est bien attendu (on teste ici uniquement l'adresse, pas le libellé)
            $destinataireExistant = false;
            foreach ($attenduDestinataires as $attenduAddress) {
                if ($aVerifierAddress->getAddress() === $attenduAddress->getAddress()) {
                    $destinataireExistant = true;
                    break;
                }
            }
            if (!$destinataireExistant) {
                $debugMessage = print_r(['a verifier' => $aVerifierDestinataires, 'attendu' => $attenduDestinataires], true);
                throw new \Exception("Destinataire à vérifier introuvable dans attendu.\r\n{$debugMessage}");
            }
        }

        // Retourne vrai si tout est ok
        return true;
    }

    /**
     * Teste si OPTION_ADMINS ajoute bien les services ayant le ROLE_ADMIN
     */
    public function testOptionAdmins()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_ADMINS,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['admins']
        ));
    }

    /**
     * Teste si OPTION_DEMANDEUR ajoute bien le service à l'origine de la demande
     */
    public function testOptionDemandeur()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_DEMANDEUR,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['demandeur']
        ));
    }

    /**
     * Teste si OPTION_INTERVENANTS ajoute bien les services sélectionnés comme intervenant lors de la création de la demande
     */
    public function testOptionIntervenants()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_INTERVENANTS,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['intervenants']
        ));
    }

    /**
     * Teste si OPTION_PILOTE_EQUIPE_OU_DME ajoute le pilote + l'équipe ou les services DME si non définie
     */
    public function testOptionPiloteEquipeOuDme()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['piloteequipedmes']
        ));
    }

    /**
     * Teste si OPTION_SERVICES_COMPOSANT récupère bien la liste des services contenus dans l'annuaire du composant
     */
    public function testOptionServicesComposant()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_COMPOSANT,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['composant']
        ));
    }

    /**
     * Teste si OPTION_SERVICES_IMPACTES récupère bien la liste de tous les annuaires des composants impactés
     */
    public function testOptionServicesImpactes()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_IMPACTES,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            array_merge($this->adresses['composant'], $this->adresses['impactes'])
        ));
    }

    /**
     * Teste si OPTION_SERVICES_CONSULTES récupère bien une liste vide, si pas de consultation précédente
     */
    public function testOptionServicesConsultesAnalyseEnCours()
    {
        $this->initialisationTests([
            'connexion|admin',
            ActionEnvoyer::class,
        ]);
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_CONSULTES,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['consultes']
        ));
    }

    /**
     * Teste si OPTION_SERVICES_CONSULTES récupère bien une liste vide, si pas de consultation précédente
     */
    public function testOptionServicesConsultesInstruiteSansConsultation()
    {
        $this->initialisationTests([
            'connexion|admin',
            ActionEnvoyer::class,
            ActionLancerInformation::class,
        ]);
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_CONSULTES,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['consultes']
        ));
    }

    /**
     * Teste si OPTION_SERVICES_CONSULTES récupère bien une liste vide, si pas de consultation précédente
     */
    public function testOptionServicesConsultesAccordeeSansConsultation()
    {
        $this->initialisationTests([
            'connexion|admin',
            ActionEnvoyer::class,
            ActionLancerInformation::class,
            ActionAccorder::class,
        ]);
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_CONSULTES,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['consultes']
        ));
    }

    /**
     * Teste si OPTION_SERVICES_CONSULTES récupère bien la liste des annuaires selectionnés si une consultation est en cours
     */
    public function testOptionServicesConsultesInstruiteAvecConsultation()
    {
        $this->initialisationTests([
            'connexion|admin',
            ActionEnvoyer::class,
            ActionLancerConsultation::class,
            ActionDonnerAvis::class,
            ActionInstruire::class,
        ]);
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_CONSULTES,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['consultes']
        ));
    }

    /**
     * Teste si OPTION_SERVICES_CONSULTES récupère bien la liste des annuaires selectionnés si état accordée après une consultation
     */
    public function testOptionServicesConsultesAccordeeAvecConsultation()
    {
        $this->initialisationTests([
            'connexion|admin',
            ActionEnvoyer::class,
            ActionLancerConsultation::class,
            ActionDonnerAvis::class,
            ActionInstruire::class,
            ActionAccorder::class,
        ]);
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SERVICES_CONSULTES,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['consultes']
        ));
    }

    /**
     * Teste si OPTION_SI2A récupère bien la liste des mails SI2A
     */
    public function testOptionSi2a()
    {
        $this->initialisationTests();
        $destinatairesCollection = new DestinatairesCollection(
            $this->testDemande,
            [
                DestinatairesCollection::OPTION_SI2A,
            ],
            static::getEm()
        );
        $this->assertTrue($this->verifieDestinataires(
            $destinatairesCollection->getDestinataires(),
            $this->adresses['si2a']
        ));
    }
}
