<?php

namespace App\DataFixtures\References;

use App\Entity\References\Mission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MissionFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return Mission
     */
    private function referenceFactory(string $label): Mission
    {
        $reference = new Mission();
        $reference->setLabel($label);
        $reference->setAjouteLe(new \DateTime());

        if ($label === "DME") {
            $reference->setSupprimeLe(new \DateTime());
        }

        return $reference;
    }

    /**
     * Permet de récupérer les données des références à créer
     * @return array|string[]
     */
    public function getReferences(): array
    {
        return [
            'Assistance',
            'AT',
            'Développement',
            'ESI hebergeur',
            'Service (pour information)',
            'EA Exploitant Applicatif',
            'ES Exploitant Système',
            'Intégration Applicative',
            'Intégration Inter-Applicative',
            'Intégration de l\'Exploitabilité',
            'MOA',
            'MOE',
            'MOA Associée',
            'MOE Déléguée',
            'DME',
            'Exploitant des Outils Mutualisés',
            'Exploitants des Infrastructures Mutualisées',
            'Scrum master',
            'Product Owner',
            'Dev Team',
            'Equipe Ops'
        ];
    }

    /**
     * Génère des références en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // On crée les références
        foreach ($this->getReferences() as $label) {
            $reference = $this->referenceFactory($label);
            $manager->persist($reference);
        }

        // On envoie les nouveaux objets en base de données
        $manager->flush();
    }
}
