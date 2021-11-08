<?php

namespace App\DataFixtures\References;

use App\Entity\References\StatutMep;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class StatutMepFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return StatutMep
     */
    private function referenceFactory(string $label): StatutMep
    {
        $reference = new StatutMep();
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
            'PROJET',
            'CONFIRME',
            'ARCHIVE',
            'ERREUR',
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
