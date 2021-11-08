<?php

namespace App\Tests\Meteo;

use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

class StatistiquesTest extends UserWebTestCase
{
    const URL_BASE = "/meteo/statistiques";

    /**
     * ------ Fonctions privée ------
     */
    /**
     * Fonction permettant d'initialiser un client avec un role prédéfini
     *
     * @param string $role
     * @return KernelBrowser
     */
    private function initialisationClient(string $role): KernelBrowser
    {
        if ($role === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($role);
            $client = static::createClientLoggedAs($service);
        }
        return $client;
    }

    /**
     * Fonction permettant d'effectuer un test d'accès sur une page statistique
     *
     * @param KernelBrowser $client
     * @param string        $suffixUrl
     * @param int           $statusCode
     * @param string        $titre
     */
    private function accesStatistiques(KernelBrowser &$client, string $suffixUrl, int $statusCode, string $titre)
    {
        $client->request(Request::METHOD_GET, self::URL_BASE . $suffixUrl);
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains($titre);
            $this->assertSelectorTextContains('.page-header h2', $titre);
        }
    }

    /**
     * ------ Fonctions de tests ------
     */
    /**
     * Teste d'accès à la partie "Répartition des évènements et des indisponibilités"
     * @dataProvider getAccesRepartitionDesEvenementsEtDesIndisponibilitesRoles
     *
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesRepartitionDesEvenementsEtDesIndisponibilites(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesStatistiques(
            $client,
            '/repartition',
            $statusCode,
            'Répartition des évènements et des indisponibilités'
        );
    }
    public function getAccesRepartitionDesEvenementsEtDesIndisponibilitesRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ],
        ];
    }

    /**
     * Teste d'accès à la partie "Historique Météo des composants"
     * @dataProvider getAccesHistoriqueMeteoDesComposantsRoles
     *
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesHistoriqueMeteoDesComposants(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesStatistiques(
            $client,
            '/historique',
            $statusCode,
            'Historique Météo des composants'
        );
    }
    public function getAccesHistoriqueMeteoDesComposantsRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ],
        ];
    }

    /**
     * Teste d'accès à la partie "Taux de disponibilité sur une période"
     * @dataProvider getAccesTauxDeDisponibiliteSurUnePeriodeRoles
     *
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesTauxDeDisponibiliteSurUnePeriode(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesStatistiques(
            $client,
            '/taux-disponibilite',
            $statusCode,
            'Taux de disponibilité des composants'
        );
    }
    public function getAccesTauxDeDisponibiliteSurUnePeriodeRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ],
        ];
    }

    /**
     * Teste d'accès à la partie "Statistiques des demandes d'intervention"
     * @dataProvider getAccesStatistiquesDesDemandesDInterventionRoles
     *
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesStatistiquesDesDemandesDIntervention(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesStatistiques(
            $client,
            '/interventions',
            $statusCode,
            'Statistiques des demandes d\'intervention'
        );
    }
    public function getAccesStatistiquesDesDemandesDInterventionRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ],
        ];
    }

    /**
     * Teste d'accès à la partie "Disponibilité des composants GESIP"
     * @dataProvider getAccesDisponibiliteDesComposantsGesipRoles
     *
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesDisponibiliteDesComposantsGesip(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesStatistiques(
            $client,
            '/taux-indisponibilites',
            $statusCode,
            'Pourcentage d\'indisponibilité'
        );
    }
    public function getAccesDisponibiliteDesComposantsGesipRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ],
        ];
    }

    /**
     * Teste d'accès à la partie "Statistiques avancées sur les interventions"
     * @dataProvider getAccesStatistiquesAvanceesSurLesInterventionsRoles
     *
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesStatistiquesAvanceesSurLesInterventions(string $role, int $statusCode)
    {
        $client = $this->initialisationClient($role);
        $this->accesStatistiques(
            $client,
            '/interventions-avancees',
            $statusCode,
            'Statistiques avancées sur les interventions'
        );
    }
    public function getAccesStatistiquesAvanceesSurLesInterventionsRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 403 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      403 ],
        ];
    }
}
