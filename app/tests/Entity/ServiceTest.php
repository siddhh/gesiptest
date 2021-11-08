<?php

namespace App\Tests\Entity;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\Service;

class ServiceTest extends KernelTestCase
{
    /**
     * Teste la génération de mot de passe
     */
    public function testGenerationMotdepasse()
    {
        // genere un lot de mot de passe
        $iterations = 100;
        $valides = 0;
        $motdepasses = [];
        for ($i =0; $i < $iterations; $i++) {
            $motdepasse = Service::generationMotdepasse();
            (is_string($motdepasse) && strlen($motdepasse) > 0 && strlen($motdepasse) <= 30) && $valides++;
            $motdepasses[] = $motdepasse;
        }
        // teste si le format des mots de passe générés est correct
        $this->assertEquals($iterations, $valides);
        // teste si tous les mots de passe générés sont différents
        $dedoubleMotdepasses = array_unique($motdepasses, SORT_STRING);
        $this->assertTrue(count(array_unique($motdepasses)) == $iterations);
    }

    /**
     * Test de la fonction validerBalf() pour un service donné
     */
    public function testValidationBafService(): void
    {
        // On crée un service
        $service = new Service();

        // On test avant validation que le champ de date est vide
        $this->assertNull($service->getDateValidationBalf());

        // On valide la balf
        $service->validerBalf();

        // On vérifie que le champ de date est bien rempli maintenant
        $this->assertNotNull($service->getDateValidationBalf());
    }
}
