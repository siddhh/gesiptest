<?php

namespace App\DataFixtures\Demande;

use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\DemandeIntervention;
use App\Entity\Demande\Impact;
use App\Entity\Demande\ImpactReel;
use App\Entity\Demande\SaisieRealise;
use App\Entity\References\MotifIntervention;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use App\DataFixtures\ComposantFixture;
use App\DataFixtures\ServiceFixture;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatTerminee;
use App\Workflow\MachineEtat;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker;

class InterventionFixture extends Fixture implements DependentFixtureInterface
{

    /**
     * On déclare les dépendances des fixtures dont nous avons besoin par la suite
     * (On est donc sûr que les données seront chargées)
     * @return array
     */
    public function getDependencies()
    {
        return [
            ComposantFixture::class,
            ServiceFixture::class,
        ];
    }

    /**
     * Génère des demandes d'intervention en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // On initialise faker
        $faker = Faker\Factory::create('fr_FR');
        // Récupère des objets pour générer une demande d'intervention
        $annuaires = $manager->getRepository(Annuaire::class)->findAll();
        $motifs = $manager->getRepository(MotifIntervention::class)->findAll();
        $natures = $manager->getRepository(NatureImpact::class)->findAll();
        $composants = $manager->getRepository(Composant::class)->findAll();
        $services = $manager->getRepository(Service::class)->findAll();
        $statusListe = array_keys(MachineEtat::getEtatLibelles());
        $etatsAutorisantSaisieRealise = [
            EtatInterventionEnCours::class,
            EtatRefusee::class,
            EtatRenvoyee::class,
            EtatSaisirRealise::class,
            EtatTerminee::class,
        ];
        $servicesExploitants = [];
        foreach ($services as $service) {
            if ($service->getEstServiceExploitant()) {
                $servicesExploitants[] = $service;
            }
        }
        // On génère les demandes...
        for ($i = 0; $i < 50; $i++) {
            // génere une demande d'intervention
            $di = new DemandeIntervention();
            $di->genererNumero();
            $di->setNumero($di->getNumero() . '_' . $i);
            $di->setDemandePar($services[rand(0, count($services) - 1)]);
            $di->setComposant($composants[rand(0, count($composants) - 1)]);
            $di->setMotifIntervention($motifs[rand(0, count($motifs) - 1)]);
            $di->setNatureIntervention(rand(0, 10) < 8 ? DemandeIntervention::NATURE_NORMAL : DemandeIntervention::NATURE_URGENT);
            $di->setPalierApplicatif(rand(0, 1) > 0);
            $di->setDescription($faker->text(256));
            $di->setSolutionContournement($faker->optional(7, '')->text(128));
            $startIntervention = time() - 200000 + rand(0, 500000);
            $minEndIntervention = $startIntervention + rand(0, 10000);
            $maxEndIntervention = $minEndIntervention + rand(0, 10000);
            $di->setDateDebut(new \DateTime("@$startIntervention"));
            $di->setDateFinMini(new \DateTime("@$minEndIntervention"));
            $di->setDateFinMax(new \DateTime("@$maxEndIntervention"));
            $di->setDureeRetourArriere(rand(0, 200));
            $di->setStatus($statusListe[rand(0, count($statusListe) - 1)]);
            // Ajoute de nouveaux services dans l'annuaire
            $exploitantServiceIds = [];
            for ($j = 0; $j < rand(1, 5); $j++) {
                $annuaire = $annuaires[rand(0, count($annuaires) - 1)];
                $exploitantServiceIds[] = $annuaire->getService()->getId();
                $di->addService($annuaire);
            }
            // Ajoute des exploitants externes de temps en temps
            if (rand(0, 10) > 8) {
                for ($j = 0; $j < rand(1, 3); $j++) {
                    $serviceExploitant = $servicesExploitants[rand(0, count($servicesExploitants) - 1)];
                    if (!in_array($serviceExploitant->getId(), $exploitantServiceIds)) {
                        $di->addExploitantExterieur($serviceExploitant);
                    }
                }
            }
            // Ajoute des impacts
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
                $di->addImpact($impact);
                $manager->persist($impact);
            }
            // Ajoute des impacts réels pour simuler une saisie du réalisé sur certains états
            if (in_array($di->getStatus(), $etatsAutorisantSaisieRealise)) {
                for ($j = 0; $j < rand(0, 3); $j++) {
                    $saisieRealise = new SaisieRealise();
                    $saisieRealise->setService($services[rand(0, count($services) - 1)]);
                    $saisieRealise->setResultat(rand() ? 'ok': 'ko');
                    $saisieRealise->setCommentaire($faker->optional(3, null)->text(128));
                    $manager->persist($saisieRealise);
                    for ($k = 0; $k < rand(0, 3); $k++) {
                        $impactReel = new ImpactReel();
                        $impactReel->setService($services[rand(0, count($services) - 1)]);
                        $impactReel->setNumeroOrdre($k + 1);
                        $startImpactReel = $startIntervention + rand(0, $maxEndIntervention);
                        $endImpactReel = $startImpactReel + rand(0, $maxEndIntervention - $startImpactReel);
                        $impactReel->setDateDebut(new \DateTime("@$startImpactReel"));
                        $impactReel->setDateFin(new \DateTime("@$endImpactReel"));
                        $impactReel->setNature($natures[rand(0, count($natures) - 1)]);
                        $impactReel->setCommentaire($faker->optional(3, null)->text(128));
                        for ($l = 0; $l < rand(0, 3); $l++) {
                            $impactReel->addComposant($composants[rand(0, count($composants) - 1)]);
                        }
                        $manager->persist($impactReel);
                        $saisieRealise->addImpactReel($impactReel);
                    }
                    $di->addSaisieRealise($saisieRealise);
                }
            }
            $manager->persist($di);
        }
        $manager->flush();
    }
}
