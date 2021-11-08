<?php

namespace App\DataFixtures\References;

use App\Entity\References\Usager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UsagerFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return Usager
     */
    private function referenceFactory(string $label): Usager
    {
        $reference = new Usager();
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
            'Externe',
            'Externe / Coloc',
            'Externe / Partenaires',
            'Externe / usagers part',
            'Externe / usagers pro',
            'Interne / Agent',
            'Mixte',
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
