<?php

namespace App\Tests\Entity\Composant;

use App\Entity\Composant\Annuaire;
use App\Entity\Service;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AnnuaireTest extends KernelTestCase
{
    /**
     * Teste la récupération de la balf si non précisé dans l'annuaire
     */
    public function testRecuperationBalfSiNonPrecise()
    {
        $service = new Service();
        $service->setEmail("balf@balf.test");

        $annuaire = new Annuaire();
        $annuaire->setService($service);

        $this->assertEquals($annuaire->getBalf(), $service->getEmail());
    }

    /**
     * Teste la récupération de la balf si précisé dans l'annuaire
     */
    public function testRecuperationBalfSiPrecise()
    {
        $service = new Service();
        $service->setEmail("balf@balf.test");

        $annuaire = new Annuaire();
        $annuaire->setService($service);
        $annuaire->setBalf("toto@balf.test");

        $this->assertEquals($annuaire->getBalf(), "toto@balf.test");
    }
}
