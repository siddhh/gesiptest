<?php

namespace App\DataFixtures\References;

use App\Entity\References\NatureImpact;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NatureImpactFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return NatureImpact
     */
    private function referenceFactory(string $label): NatureImpact
    {
        $reference = new NatureImpact();
        $reference->setLabel($label);
        $reference->setAjouteLe(new \DateTime());
        return $reference;
    }

    /**
     * Permet de récupérer les données des références à créer
     * @return array|string[]
     */
    public function getReferences(): array
    {
        return [
            'Aucun impact',
            'Fonctionnalités réduites',
            'Impact Ponctuel MMA',
            'Indisponibilité partielle',
            'Indisponibilité totale',
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
