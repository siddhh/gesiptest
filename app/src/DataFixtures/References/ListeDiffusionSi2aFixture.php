<?php

namespace App\DataFixtures\References;

use App\Entity\References\ListeDiffusionSi2a;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ListeDiffusionSi2aFixture extends Fixture
{

    /**
     * Permet de créer une référence
     *
     * @param string $label
     * @return ListeDiffusionSi2a
     */
    private function referenceFactory(array $data): ListeDiffusionSi2a
    {
        $reference = new ListeDiffusionSi2a();
        $reference->setLabel($data['label']);
        $reference->setFonction($data['fonction']);
        $reference->setBalp($data['balp']);
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
                'label' => 'Christine GRAVOSQUI',
                'fonction' => 'Chef de Bureau SI2',
                'balp' => 'christine.gravosqui@dgfip.finances.gouv.fr',
            ],
            [
                'label' => 'Jean-François GUILBERT',
                'fonction' => 'Adjoint Chef de Bureau SI2',
                'balp' => 'jean-francois.guilbert@dgfip.finances.gouv.fr',
            ],
            [
                'label' => 'Laurent FRAISSE',
                'fonction' => 'Responsable DME',
                'balp' => 'laurent-l.fraisse@dgfip.finances.gouv.fr',
            ],
            [
                'label' => 'Jean-Cristophe POMMIER',
                'fonction' => 'Adjoint DME',
                'balp' => 'jean-christophe.pommier@dgfip.finances.gouv.fr',
            ],
            [
                'label' => 'Claude GATTI',
                'fonction' => 'Responsable Equipe CS1',
                'balp' => 'claude.gatti@dgfip.finances.gouv.fr',
            ],
            [
                'label' => 'Eric REBOUILLET-PETIOT',
                'fonction' => 'Responsable Equipe CS2',
                'balp' => 'eric.rebouillet-petiot@dgfip.finances.gouv.fr',
            ],
            [
                'label' => 'Marie-Pierre LIGOUT',
                'fonction' => 'Responsable Equipe EBEME',
                'balp' => 'marie-pierre.ligout@dgfip.finances.gouv.fr',
            ],
        ];
    }

    /**
     * Génère des références en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // On crée les références
        foreach ($this->getReferences() as $data) {
            $reference = $this->referenceFactory($data);
            $manager->persist($reference);
        }

        // On envoie les nouveaux objets en base de données
        $manager->flush();
    }
}
