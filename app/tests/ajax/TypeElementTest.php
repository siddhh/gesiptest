<?php

namespace App\Tests\Ajax;

use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\References\TypeElement;

class TypeElementTest extends UserWebTestCase
{
    /** @var string */
    protected static $urlTypeElement = "/ajax/reference/type_element";

    /**
     * Teste d'accès au webservice
     * @dataProvider getAccesParRoles
     */
    public function testTypeElementControleDesAcces(string $roles, int $statusCode)
    {
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        $client->request(Request::METHOD_POST, static::$urlTypeElement);
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
    }
    public function getAccesParRoles(): array
    {
        return [
            'NON_CONNECTE' => [
                'NON_CONNECTE',
                302
            ],
            Service::ROLE_ADMIN => [
                Service::ROLE_ADMIN,
                422
            ],
            Service::ROLE_DME => [
                Service::ROLE_DME,
                403
            ],
            Service::ROLE_INTERVENANT => [
                Service::ROLE_INTERVENANT,
                403
            ],
            Service::ROLE_INVITE => [
                Service::ROLE_INVITE,
                403
            ]
        ];
    }

    /**
     * Supprime le type d'element
     */
    public function testTypeElementSupprime()
    {
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        $client = static::createClientLoggedAs($service);
        $typeElementIdASupprimer = 3;
        $client->xmlHttpRequest(Request::METHOD_DELETE, static::$urlTypeElement . '/' . $typeElementIdASupprimer);
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        // vérifie qu'on a seulement bien 2 types d'élements (2 non affichés, car supprimés)
        $data = json_decode($response->getContent(), true);
        $referenceSupprimeId = $data['data']['supprime_id'];
        $this->assertEquals($referenceSupprimeId, $typeElementIdASupprimer);
        // vérifie que le type d'élement a bien été supprimé (de manière "douce")
        $typeElement = self::getEmRepository(TypeElement::class)->find($typeElementIdASupprimer);
        $this->assertNotNull($typeElement->getSupprimeLe());
    }

    /**
     * Teste l'ajout d'un type d'element
     */
    public function testTypeElementAjout()
    {
        // Demande l'ajout d'un type d'element
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        $client = static::createClientLoggedAs($service);
        $label = 'LibelleTypeElement_' . uniqid();
        $client->request(
            Request::METHOD_POST,
            static::$urlTypeElement,
            [
                'type_element' => [
                    'label' => $label
                ]
            ]
        );
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsNumeric($data['data']['nouvelId']);
    }

    /**
     * Teste la modification d'un type d'element
     */
    public function testTypeElementModifie()
    {
        // Demande l'ajout d'un type d'element
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        $client = static::createClientLoggedAs($service);
        $nouveauLabel = 'LibelleTypeElement_' . uniqid();
        $client->request(
            Request::METHOD_POST,
            static::$urlTypeElement . '/2',
            [
                '_method' => 'PUT',
                'type_element' => [
                    'label' => $nouveauLabel
                ]
            ]
        );
        // Vérifie le status de la demande
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsNumeric($data['data']['nouvelId']);
        $this->assertIsNumeric($data['data']['supprimeId']);
    }
}
