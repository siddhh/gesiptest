<?php

namespace App\DataFixtures\References;

use App\Entity\References\MotifIntervention;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MotifInterventionFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return MotifIntervention
     */
    private function referenceFactory(string $label): MotifIntervention
    {
        $reference = new MotifIntervention();
        $reference->setLabel($label);
        $reference->setAjouteLe(new \DateTime());

        if ($label === "Non communiqué") {
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
            'Chef de Bureau Si-2A',
            'Maintenance applicative',
            'Maintenance technique',
            'Opération d\'exploitation',
            'Opération de travaux sur site',
            'Ouverture de droits',
            'Ouverture de flux',
            'Résolution d\'incident',
            'Incident',
            'Non communiqué'
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
