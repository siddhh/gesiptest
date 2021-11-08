<?php

namespace App\DataFixtures\Calendrier;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Entity\Pilote;
use App\Entity\References\GridMep;
use App\Entity\References\StatutMep;
use App\Entity\Service;
use App\DataFixtures\ComposantFixture;
use App\DataFixtures\PiloteFixture;
use App\DataFixtures\ServiceFixture;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker;

class MepssiFixture extends Fixture implements DependentFixtureInterface
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
            PiloteFixture::class,
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
        $services = $manager->getRepository(Service::class)->getPilotageEquipes();
        $gridMeps = $manager->getRepository(GridMep::class)->findAll();
        $statusMeps = $manager->getRepository(StatutMep::class)->findAll();
        $visibilite = ['DME', 'SI2A', 'SSI'];
        for ($iMeps = 0; $iMeps < 50; $iMeps++) {
            // Recharge les liste des objets existants
            $composants = $manager->getRepository(Composant::class)->findAll();
            $pilotes = $manager->getRepository(Pilote::class)->findAll();
            $demandeInterventions = $manager->getRepository(DemandeIntervention::class)->findAll();
            // génere une Mep Ssi
            $mepSsi = new MepSsi();
            $mepSsi->setDemandePar($services[rand(0, count($services) - 1)]);
            $mepSsi->setPalier($faker->text(16));
            for ($i = 0; $i < rand(1, 3); $i++) {
                $composant = array_splice($composants, rand(0, count($composants) - 1), 1)[0];
                $mepSsi->addComposant($composant);
            }
            $mepSsi->setvisibilite($visibilite[rand(0, count($visibilite) - 1)]);
            $mepSsi->setEquipe($services[rand(0, count($services) - 1)]);
            for ($i = 0; $i < rand(1, 3); $i++) {
                $pilote = array_splice($pilotes, rand(0, count($pilotes) - 1), 1)[0];
                $mepSsi->addPilote($pilote);
            }
            for ($i = 0; $i < rand(0, 3); $i++) {
                $demandeIntervention = array_splice($demandeInterventions, rand(0, count($demandeInterventions) - 1), 1)[0];
                $mepSsi->addDemandesIntervention($demandeIntervention);
            }
            $tsNow = time();
            rand(0, 1) && $mepSsi->setLep(new \DateTime('@' . ($tsNow - 8640000 + rand(0, 17280000))));
            $tsMepDebut = $tsNow - 8640000 + rand(0, 17280000);
            rand(0, 1) && $mepSsi->setMepDebut(new \DateTime('@' . $tsMepDebut));
            rand(0, 1) && $mepSsi->setMepFin(new \DateTime('@' . ($tsMepDebut + rand(0, 17280000))));
            $mepSsi->setMes(new \DateTime('@' . ($tsNow - 8640000 + rand(0, 17280000))));
            rand(0, 1) && $mepSsi->addGrid($gridMeps[rand(0, count($gridMeps) - 1)]);
            $mepSsi->setStatut($statusMeps[rand(0, count($statusMeps) - 1)]);
            $mepSsi->setDescription($faker->optional(.5)->text(128));
            $mepSsi->setImpacts($faker->optional(.5)->text(128));
            $mepSsi->setRisques($faker->optional(.5)->text(128));
            $motClefs = [];
            for ($iMotClefs = 0; $iMotClefs < rand(0, 5); $iMotClefs++) {
                $motClefs[] = mb_strtolower($faker->lastName);
            }
            $mepSsi->setMotsClefs(count($motClefs) > 0 ? implode('; ', $motClefs): null);
            // persiste
            $manager->persist($mepSsi);
        }
        // Enregistre les modifications
        $manager->flush();
    }
}
