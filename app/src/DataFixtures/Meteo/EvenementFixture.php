<?php

namespace App\DataFixtures\Meteo;

use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\Meteo\Evenement;
use App\Entity\References\ImpactMeteo;
use App\Entity\References\MotifIntervention;
use App\DataFixtures\ComposantFixture;
use App\DataFixtures\ServiceFixture;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker;

class EvenementFixture extends Fixture implements DependentFixtureInterface
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
        $composants = $manager->getRepository(Composant::class)->findAll();
        $services = $manager->getRepository(Service::class)->findAll();
        $impactMeteos = $manager->getRepository(ImpactMeteo::class)->findAll();
        $motifInterventions = $manager->getRepository(MotifIntervention::class)->findAll();

        // On génère n elements...
        for ($i = 0; $i < 50; $i++) {
            // génere un evenement aléatoire
            $evenement = new Evenement();
            $startEvenement = time() - 2000000 + rand(0, 2000000);
            $endEvenement = $startEvenement + rand(0, 50000);
            $evenement->setDebut(new \DateTime("@$startEvenement"));
            $evenement->setFin(new \DateTime("@$endEvenement"));
            $evenement->setComposant($composants[rand(0, count($composants) - 1)]);
            $evenement->setImpact($impactMeteos[rand(0, count($impactMeteos) - 1)]);
            $evenement->setTypeOperation($motifInterventions[rand(0, count($motifInterventions) - 1)]);
            $evenement->setDescription($faker->optional(2, '')->text(128));
            $evenement->setCommentaire($faker->optional(4, '')->text(256));
            $evenement->setSaisiePar($services[rand(0, count($services) - 1)]);
            $manager->persist($evenement);
        }
        $manager->flush();
    }
}
