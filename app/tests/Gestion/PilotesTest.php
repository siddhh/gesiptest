<?php

namespace App\Tests\Gestion;

use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * TODO: Faire d'autres tests !
 */
class PilotesTest extends UserWebTestCase
{
    /**
     * Teste d'accès à la partie gestion des services
     * @dataProvider getAccesParRoles
     */
    public function testGestionPilotesControleDesAcces(string $roles, int $statusCode)
    {
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        $client->request(Request::METHOD_GET, '/gestion/pilotes');

        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Gestion des pilotes');
            $this->assertSelectorTextContains('.page-header h2', 'Liste des pilotes');
        }
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
                200
            ],
            Service::ROLE_DME => [
                Service::ROLE_DME,
                200
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
}
