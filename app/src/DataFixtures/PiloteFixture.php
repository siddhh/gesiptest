<?php

namespace App\DataFixtures;

use Faker;
use App\Entity\Pilote;
use App\Entity\Service;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class PiloteFixture extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [ServiceFixture::class];
    }

    /**
     * Génère des pilotes en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        $faker = Faker\Factory::create('fr_FR');

        $repository = $manager->getRepository(Service::class);
        $serviceTableau = $repository->findAll();

        for ($i = 0; $i < 16; $i++) {
            $r = rand(0, (count($serviceTableau) - 1));
            $service = $serviceTableau[$r];
            $nom = $faker->lastname;
            $prenom = $faker->firstname;
            $email = uniqid() . "@dgfip.finances.gouv.fr";

            $uniqId = uniqid();
            $pilote = new Pilote();
            $pilote->setNom($nom);
            $pilote->setPrenom($prenom);
            $pilote->setBalp($email);
            $pilote->setEquipe($service);

            $manager->persist($pilote);
        }
        // Génère un pilote spécifique pour tester
        $manager->flush();
    }
}
