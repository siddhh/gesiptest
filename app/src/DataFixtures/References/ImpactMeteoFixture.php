<?php

namespace App\DataFixtures\References;

use App\Entity\References\ImpactMeteo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ImpactMeteoFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return ImpactMeteo
     */
    private function referenceFactory(string $label): ImpactMeteo
    {
        $reference = new ImpactMeteo();
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
            'Accès impossible',
            'Aucun impact',
            'Fonctionnalités réduites',
            'Fonctionnement dégradé',
            'Impact ponctuel MMA',
            'Indisponibilité partielle',
            'Indisponibilité programmée',
            'Indisponibilité totale',
            'Retard majeur dans la mise à jour des données',
            'Retard mineur dans la mise à jour des données',
            'Transparent pour les utilisateurs',
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
