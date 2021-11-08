<?php

namespace App\DataFixtures\References;

use App\Entity\References\MotifRenvoi;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MotifRenvoiFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param array $label
     * @return MotifRenvoi
     */
    private function referenceFactory(array $data): MotifRenvoi
    {
        $reference = new MotifRenvoi();
        $reference->setLabel($data['label']);
        $reference->setType($data['type']);
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
            [
                'label' => 'Composant concerné',
                'type' => 'Demande',
            ],
            [
                'label' => 'Nature d\'intervention',
                'type' => 'Demande',
            ],
            [
                'label' => 'Motif d\'interventions',
                'type' => 'Demande',
            ],
            [
                'label' => 'Palier applicatif',
                'type' => 'Demande',
            ],
            [
                'label' => 'Description',
                'type' => 'Demande',
            ],
            [
                'label' => 'Intervention réalisée par',
                'type' => 'Demande',
            ],
            [
                'label' => 'Solution de contournement existante',
                'type' => 'Demande',
            ],
            [
                'label' => 'Période - Date Heure Intervention',
                'type' => 'Impact',
            ],
            [
                'label' => 'Période - Date Heure de fin min',
                'type' => 'Impact',
            ],
            [
                'label' => 'Période – Date Heure de fin max',
                'type' => 'Impact',
            ],
            [
                'label' => 'Durée retour arrière',
                'type' => 'Impact',
            ],
            [
                'label' => 'Nature',
                'type' => 'Impact',
            ],
            [
                'label' => 'Impact',
                'type' => 'Impact',
            ],
            [
                'label' => 'Détail impact',
                'type' => 'Impact',
            ],
            [
                'label' => 'Composants impactés',
                'type' => 'Impact',
            ],
            [
                'label' => 'Ajouter un impact',
                'type' => 'Impact',
            ],
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
