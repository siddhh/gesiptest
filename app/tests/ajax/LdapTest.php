<?php

namespace App\Tests\Ajax;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Controller\Ajax\LdapController;
use App\Service\LdapService;
use Symfony\Component\HttpFoundation\Request;

class LdapTest extends WebTestCase
{
    public function testRechercheStructuresMoinsDe3Caracteres()
    {
        $requete = new Request(['recherche' => 'es']);
        $mockLdapService = $this->getMockBuilder(LdapService::class)->disableOriginalConstructor()->getMock();
        $mockLdapService->expects($this->never())
                        ->method('rechercheStructures');

        $ldapController = new LdapController();
        $reponse = $ldapController->ldapRechercheStructures($mockLdapService, $requete);

        $this->assertEquals(200, $reponse->getStatusCode());
        $this->assertJson($reponse->getContent());

        $json = (array)json_decode($reponse->getContent());
        $this->assertArrayHasKey('recherche', $json);
        $this->assertEquals('es', $json['recherche']);

        $this->assertArrayHasKey('donnees', $json);
        $this->assertEquals([], $json['donnees']);
    }

    public function testRechercheStructuresDePlusDe3Caracteres()
    {
        $requete = new Request(['recherche' => 'esi']);
        $mockLdapService = $this->getMockBuilder(LdapService::class)->disableOriginalConstructor()->getMock();
        $mockLdapService->expects($this->once())
                        ->method('rechercheStructures')
                        ->will($this->returnValue([['nom' => 'un nom', 'mail' => 'un mail']]));

        $ldapController = new LdapController();
        $reponse = $ldapController->ldapRechercheStructures($mockLdapService, $requete);

        $this->assertEquals(200, $reponse->getStatusCode());
        $this->assertJson($reponse->getContent());

        $json = (array)json_decode($reponse->getContent());
        $this->assertArrayHasKey('recherche', $json);
        $this->assertEquals('esi', $json['recherche']);

        $this->assertArrayHasKey('donnees', $json);
        $this->assertEquals(1, count($json['donnees']));

        $this->assertArrayHasKey('nom', (array)$json['donnees'][0]);
        $this->assertArrayHasKey('mail', (array)$json['donnees'][0]);
    }

    public function testRecherchePersonnesMoinsDe3Caracteres()
    {
        $requete = new Request(['recherche' => 'es']);
        $mockLdapService = $this->getMockBuilder(LdapService::class)->disableOriginalConstructor()->getMock();
        $mockLdapService->expects($this->never())->method('recherchePersonnes');

        $ldapController = new LdapController();
        $reponse = $ldapController->ldapRecherchePersonnes($mockLdapService, $requete);

        $this->assertEquals(200, $reponse->getStatusCode());
        $this->assertJson($reponse->getContent());

        $json = (array)json_decode($reponse->getContent());
        $this->assertArrayHasKey('recherche', $json);
        $this->assertEquals('es', $json['recherche']);

        $this->assertArrayHasKey('donnees', $json);
        $this->assertEquals([], $json['donnees']);
    }

    public function testRecherchePersonnesDePlusDe3Caracteres()
    {
        $requete = new Request(['recherche' => 'esi']);
        $mockLdapService = $this->getMockBuilder(LdapService::class)->disableOriginalConstructor()->getMock();
        $mockLdapService->expects($this->once())
                        ->method('recherchePersonnes')
                        ->will($this->returnValue([['nom' => 'un nom', 'mail' => 'un mail']]));

        $ldapController = new LdapController();
        $reponse = $ldapController->ldapRecherchePersonnes($mockLdapService, $requete);

        $this->assertEquals(200, $reponse->getStatusCode());
        $this->assertJson($reponse->getContent());

        $json = (array)json_decode($reponse->getContent());
        $this->assertArrayHasKey('recherche', $json);
        $this->assertEquals('esi', $json['recherche']);

        $this->assertArrayHasKey('donnees', $json);
        $this->assertEquals(1, count($json['donnees']));

        $this->assertArrayHasKey('nom', (array)$json['donnees'][0]);
        $this->assertArrayHasKey('mail', (array)$json['donnees'][0]);
    }
}
