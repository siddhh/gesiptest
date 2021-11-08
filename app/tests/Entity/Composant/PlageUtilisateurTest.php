<?php

namespace App\Tests\Entity\Composant;

use App\Entity\Composant\PlageUtilisateur;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PlageUtilisateurTest extends KernelTestCase
{
    /**
     * Teste la récupération d'un jour
     * @dataProvider getJours
     */
    public function testRecuperationChaineJour(int $jourInt, string $jourChaine)
    {
        $plage = new PlageUtilisateur();
        $plage->setJour($jourInt);
        $plage->setDebut(new \DateTime('12:00'));
        $plage->setFin(new \DateTime('20:00'));

        $this->assertEquals($jourChaine, $plage->getJourChaine());
    }
    public function getJours(): array
    {
        return [
            'Lundi' => [
                1,
                'Lundi'
            ],
            'Mardi' => [
                2,
                'Mardi'
            ],
            'Mercredi' => [
                3,
                'Mercredi'
            ],
            'Jeudi' => [
                4,
                'Jeudi'
            ],
            'Vendredi' => [
                5,
                'Vendredi'
            ],
            'Samedi' => [
                6,
                'Samedi'
            ],
            'Dimanche' => [
                7,
                'Dimanche'
            ]
        ];
    }

    /**
     * Test le comptage du temps total entre le début et la fin de la période
     * @dataProvider getHoraires
     */
    public function testCalculDuTempsTotal(string $debut, string $fin, string $interval)
    {
        $plage = new PlageUtilisateur();
        $plage->setJour(1);
        $plage->setDebut(new \DateTime($debut));
        $plage->setFin(new \DateTime($fin));

        $this->assertEquals($interval, $plage->getTempsTotalEnMinutes());
    }
    public function getHoraires(): array
    {
        return [
            '12:00 -> 20:00' => [
                '12:00',
                '20:00',
                480,
            ],
            '10:00 -> 20:00' => [
                '10:00',
                '20:00',
                600,
            ],
            '15:30 -> 20:00' => [
                '15:30',
                '20:30',
                300,
            ],
            '18:30 -> 20:00' => [
                '18:30',
                '20:00',
                90,
            ],
            '20:00 -> 10:00' => [
                '20:00',
                '10:00',
                0,
            ],
            '00:00 -> 24:00' => [
                '00:00',
                '00:00',
                1440
            ],
            '00:00 -> 00:01' => [
                '00:00',
                '00:01',
                1
            ],
            '00:00 -> 23:59' => [
                '00:00',
                '23:59',
                1439
            ],
            '12:00 -> 24:00' => [
                '12:00',
                '00:00',
                720
            ]
        ];
    }
}
