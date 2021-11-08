<?php

namespace App\DataFixtures;

use App\Entity\References\Mission;
use App\Entity\References\TypeElement;
use Faker;
use App\DataFixtures\References\DomaineFixture;
use App\DataFixtures\References\UsagerFixture;
use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\References\Domaine;
use App\Entity\References\Usager;
use App\Entity\Service;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ComposantFixture extends Fixture implements DependentFixtureInterface
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
            PiloteFixture::class,
            UsagerFixture::class,
            DomaineFixture::class
        ];
    }

    /**
     * Génère des composants en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // On initialise faker
        $faker = Faker\Factory::create('fr_FR');

        // On récupère certaines infos
        $services = $manager->getRepository(Service::class)->findAll();
        $pilotes = $manager->getRepository(Pilote::class)->findAll();
        $missions = $manager->getRepository(Mission::class)->findAll();
        $usagers = $manager->getRepository(Usager::class)->findAll();
        $domaines = $manager->getRepository(Domaine::class)->findAll();
        $typeElements = $manager->getRepository(TypeElement::class)->findAll();

        // On crée 20 composants
        for ($i = 0; $i < 20; $i++) {
            // On défini les infos générales de notre composant
            $composant = new Composant();
            $composant->setUsager($this->getRandom($usagers));
            $composant->setDomaine($this->getRandom($domaines));
            $composant->setPilote($this->getRandom($pilotes));
            $composant->setPiloteSuppleant($this->getRandom($pilotes));
            $composant->setTypeElement($this->getRandom($typeElements));
            $composant->setLabel(\mb_strtoupper($faker->lastName));
            $composant->setCodeCarto(\mb_strtoupper($faker->lastName));
            $composant->setIntitulePlageUtilisateur($faker->words(3, true));
            $composant->setMeteoActive($faker->boolean);
            $composant->setEstSiteHebergement($faker->boolean);

            // On défini les liens avec les services
            /** @var Service $equipe */
            $equipe = $this->getRandom($services);
            $equipe->setEstPilotageDme(true);
            $composant->setEquipe($equipe);
            /** @var Service $exploitant */
            $exploitant = $this->getRandom($services);
            $exploitant->setEstServiceExploitant(true);
            $composant->setExploitant($exploitant);
            /** @var Service $bureauRattachement */
            $bureauRattachement = $this->getRandom($services);
            $bureauRattachement->setEstBureauRattachement(true);
            $composant->setBureauRattachement($bureauRattachement);

            // On ajoute des plages utilisateurs (nombre d'entrées aléatoire entre 0 et 5)
            for ($y = 0; $y < rand(0, 5); $y++) {
                $plageUtilisateur = new Composant\PlageUtilisateur();
                $plageUtilisateur->setJour(rand(1, 7));
                $plageUtilisateur->setDebut(new \DateTime('0' . rand(5, 9) . ':' . (rand(0, 1)?'30':'00')));
                $plageUtilisateur->setFin(new \DateTime('1' . rand(5, 9) . ':' . (rand(0, 1)?'30':'00')));
                $manager->persist($plageUtilisateur);
                $composant->addPlagesUtilisateur($plageUtilisateur);
            }

            // On ajout des éléments dans l'annuaire du composant (nombre d'entrées aléatoire entre 2 et 5)
            for ($y = 0; $y < rand(2, 5); $y++) {
                $annuaire = new Composant\Annuaire();
                $annuaire->setService($this->getRandom($services));
                $annuaire->setMission($this->getRandom($missions));
                // on défini une balp customisée de manière aléatoire (1/3)
                if (rand(0, 3) === 0) {
                    $annuaire->setBalf($faker->uuid . '@dgfip.finances.gouv.fr');
                }
                $manager->persist($annuaire);
                $composant->addAnnuaire($annuaire);
            }

            // On persiste notre composant
            $manager->persist($composant);
        }
        // On tire la chasse
        $manager->flush();

        // On fait les liens entre composants :
        $composants = $manager->getRepository(Composant::class)->findAll();
        /** @var Composant $composant */
        foreach ($composants as $composant) {
            // On impacte un composant aléatoirement entre 2 et 5 fois
            for ($y = 0; $y < rand(2, 5); $y++) {
                $composant->addComposantsImpacte($this->getRandom($composants));
            }
        }

        // On tire à nouveau la chasse
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
