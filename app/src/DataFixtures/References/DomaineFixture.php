<?php

namespace App\DataFixtures\References;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\References\Domaine;

class DomaineFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return Domaine
     */
    private function referenceFactory(string $label): Domaine
    {
        $reference = new Domaine();
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
            'Domaine – Gestion du domaine',
            'Fiscalité – Contrôle fiscal et Contentieux',
            'Fiscalité – Foncier et Patrimoine',
            'Fiscalité – Particuliers',
            'Fiscalité – Professionnels',
            'Fiscalité – Recouvrement',
            'Gestion publique – Comptabilité',
            'Gestion publique – Dépenses de l\'Etat et Paie',
            'Gestion publique – Fonds déposés',
            'Gestion publique – Gestion comptable et financière',
            'Gestion publique – Moyens de paiement',
            'Gestion publique – Retraites et pensions',
            'Gestion publique – Valorisation et conseil',
            'Pilotage – Audit, Risques et Contrôle de gestion',
            'Pilotage – Communication',
            'SSI – Infrastructures',
            'SSI – Outillage',
            'Transverse – Budget, Moyens et Logistique',
            'Transverse – Outillage',
            'Transverse – RH',
            'Transverse – Référentiels',
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
