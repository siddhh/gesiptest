<?php

namespace App\DataFixtures\References;

use App\Entity\References\GridMep;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GridMepFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return GridMep
     */
    private function referenceFactory(string $label): GridMep
    {
        $reference = new GridMep();
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
            'Chantier prévu au PSI',
            'FDR – Fait l\'objet d’une feuille de route',
            'Impacts usagers',
            'Indisponibilité',
            'Infra transverse impactante',
            'Intervention HNO',
            'Nombreux ESI concernés',
            'REX prévu / à prévoir',
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
