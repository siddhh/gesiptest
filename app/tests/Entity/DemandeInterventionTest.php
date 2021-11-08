<?php

namespace App\Tests\Entity;

use App\Entity\DemandeIntervention;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DemandeInterventionTest extends KernelTestCase
{
    /**
     * Teste la génération de numéro
     */
    public function testGenerationNumero()
    {
        // On crée une demande
        $demandes = new DemandeIntervention();

        // On test avant la génération
        $this->assertNull($demandes->getNumero());
        $this->assertNull($demandes->getDemandeLe());

        // On génère le numéro
        $demandes->genererNumero();

        // On test que les attributs ont changés correctement
        $this->assertNotNull($demandes->getNumero());
        $this->assertNotNull($demandes->getDemandeLe());

        // On récupère la date que l'on met dans notre timezone (le numéro est dans la timezone Europe/Paris !)
        $demandeLe = clone($demandes->getDemandeLe());
        $demandeLe->setTimezone(new \DateTimeZone('Europe/Paris'));
        // On test que le numéro est bien formé
        $this->assertEquals($demandeLe->format('YmdHis'), $demandes->getNumero());
    }
}
