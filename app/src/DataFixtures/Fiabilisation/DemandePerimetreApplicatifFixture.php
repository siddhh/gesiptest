<?php

namespace App\DataFixtures\Fiabilisation;

use App\DataFixtures\ComposantFixture;
use App\DataFixtures\References\MissionFixture;
use App\DataFixtures\ServiceFixture;
use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\References\Mission;
use Faker;
use App\Entity\Composant;
use App\Entity\Service;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DemandePerimetreApplicatifFixture extends Fixture implements DependentFixtureInterface
{

    /**
     * On déclare les dépendances des fixtures dont nous avons besoin par la suite
     * (On est donc sûr que les données seront chargées)
     * @return array
     */
    public function getDependencies()
    {
        return [
            ServiceFixture::class,
            ComposantFixture::class,
            MissionFixture::class,
        ];
    }

    /**
     * Génère des demandes en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // On initialise faker
        $faker = Faker\Factory::create('fr_FR');

        // On récupère certaines infos
        $services = $manager->getRepository(Service::class)->findBy([], null, 10);
        $composants = $manager->getRepository(Composant::class)->findBy([], null, 10);
        $missions = $manager->getRepository(Mission::class)->findBy([], null, 10);

        // On crée 10 demandes
        for ($i = 0; $i < 10; $i++) {
            // On défini les infos générales de notre demande
            $demande = new DemandePerimetreApplicatif();
            $demande->setServiceDemandeur($this->getRandom($services));
            $demande->setComposant($this->getRandom($composants));
            $demande->setMission($this->getRandom($missions));
            $demande->setType($faker->randomElement([
                DemandePerimetreApplicatif::AJOUT,
                DemandePerimetreApplicatif::RETRAIT]));

            if ($i === 7) {
                $demande->setCommentaire($faker->text(100));
                $demande->accepter($this->getRandom($services));
            } elseif ($i === 8) {
                $demande->setCommentaire($faker->text(100));
                $demande->refuser($this->getRandom($services));
            } elseif ($i === 9) {
                $demande->setCommentaire($faker->text(100));
                $demande->annuler($this->getRandom($services));
            }

            // On persiste notre demande
            $manager->persist($demande);
            unset($demande);
        }
        // On tire la chasse
        $manager->flush();
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
}
