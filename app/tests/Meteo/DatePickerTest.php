<?php

namespace App\Tests\Meteo;

use App\Entity\Meteo\Publication;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DatePickerTest extends UserWebTestCase
{

    /**
     * Teste l'accès au webservice du datetimepicker
     *
     * @dataProvider getAccesParRoles
     *
     * @param string $roles
     * @param int    $statusCode
     */
    public function testAcces(string $roles, int $statusCode)
    {
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        $client->request(Request::METHOD_GET, '/ajax/meteo/datepicker/periodes-a-saisir');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());

        $client->request(Request::METHOD_GET, '/ajax/meteo/datepicker/periodes-a-saisir/depublication');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
    }

    /**
     * Provider de données pour les accès aux webservices.
     * @return array[]
     */
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
                200
            ],
            Service::ROLE_INVITE => [
                Service::ROLE_INVITE,
                200
            ]
        ];
    }

    /**
     * Teste le webservice permettant de lister les semaines déjà publiées.
     */
    public function testPeriodesAPublier()
    {
        // On crée notre client
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        $client = static::createClientLoggedAs($service);
        $em = self::getEm($client);

        // On crée notre résultat attendu
        $resultatAttendu = [];
        for ($i = 1; $i <= 31; $i++) {
            $resultatAttendu[] = '2021-01-' . str_pad($i, 2, '0', STR_PAD_LEFT);
        }

        // On effectue la requête pour janvier 2021, que l'on vérifie (31 jours en janvier)
        $client->request(Request::METHOD_GET, '/ajax/meteo/datepicker/periodes-a-saisir?month=1&year=2021');
        $reponse = $client->getResponse();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertTrue($reponse->headers->contains('Content-Type', 'application/json'));
        $this->assertJsonStringEqualsJsonString(json_encode($resultatAttendu), $reponse->getContent());

        // On ajoute une période du 07/01/2021 au 13/01/2021
        $periodeDebut = \DateTime::createFromFormat('d/m/Y H:i:s', '07/01/2021 00:00:00');
        $periodeFin = \DateTime::createFromFormat('d/m/Y H:i:s', '13/01/2021 00:00:00');
        $periode = new Publication();
        $periode->setPeriodeDebut($periodeDebut);
        $periode->setPeriodeFin($periodeFin);
        $em->persist($periode);
        $em->flush();

        // On supprime les valeurs entre 07 et 13 dans le résultat attendu
        $resultatAttendu = array_values(array_filter($resultatAttendu, function ($date) {
            $day = substr($date, 8, 2);
            return $day < 7 || $day > 13;
        }));

        // On effectue une nouvelle la requête pour janvier 2021, que l'on vérifie (31 - 7 jours en janvier)
        $client->request(Request::METHOD_GET, '/ajax/meteo/datepicker/periodes-a-saisir?month=1&year=2021');
        $reponse = $client->getResponse();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertTrue($reponse->headers->contains('Content-Type', 'application/json'));
        $this->assertJsonStringEqualsJsonString(json_encode($resultatAttendu), $reponse->getContent());
    }

    /**
     * Teste le webservice permettant de lister les semaines déjà publiées.
     */
    public function testPeriodesDejaPubliees()
    {
        // On crée notre client
        $service = self::getOneServiceFromRole(Service::ROLE_ADMIN);
        $client = static::createClientLoggedAs($service);
        $em = self::getEm($client);

        // On crée notre résultat attendu
        $resultatAttendu = [];

        // On effectue la requête pour janvier 2021, que l'on vérifie (31 jours en janvier)
        $client->request(Request::METHOD_GET, '/ajax/meteo/datepicker/periodes-a-saisir/depublication?month=1&year=2021');
        $reponse = $client->getResponse();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertTrue($reponse->headers->contains('Content-Type', 'application/json'));
        $this->assertJsonStringEqualsJsonString(json_encode($resultatAttendu), $reponse->getContent());

        // On ajoute une période du 07/01/2021 au 13/01/2021
        $periodeDebut = \DateTime::createFromFormat('d/m/Y H:i:s', '07/01/2021 00:00:00');
        $periodeFin = \DateTime::createFromFormat('d/m/Y H:i:s', '13/01/2021 00:00:00');
        $periode = new Publication();
        $periode->setPeriodeDebut($periodeDebut);
        $periode->setPeriodeFin($periodeFin);
        $em->persist($periode);
        $em->flush();

        // On ajoute les valeurs entre 07 et 13 dans le résultat attendu
        for ($i = 7; $i <= 13; $i++) {
            $resultatAttendu[] = '2021-01-' . str_pad($i, 2, '0', STR_PAD_LEFT);
        }

        // On effectue une nouvelle la requête pour janvier 2021, que l'on vérifie (31 - 7 jours en janvier)
        $client->request(Request::METHOD_GET, '/ajax/meteo/datepicker/periodes-a-saisir/depublication?month=1&year=2021');
        $reponse = $client->getResponse();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertTrue($reponse->headers->contains('Content-Type', 'application/json'));
        $this->assertJsonStringEqualsJsonString(json_encode($resultatAttendu), $reponse->getContent());
    }
}
