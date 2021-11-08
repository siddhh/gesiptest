<?php

namespace App\DataFixtures\References;

use App\Entity\References\TypeElement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TypeElementFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return TypeElement
     */
    private function referenceFactory(string $label): TypeElement
    {
        $reference = new TypeElement();
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
            'Standard',
            'Non MOA – Admin',
            'Non MOA - Standard',
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
