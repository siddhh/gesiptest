<?php

namespace App\Tests\Calendrier;

use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CalendrierGlobalTest extends UserWebTestCase
{

/**

 * - Tester la vue interapplicative : /calendrier/inter-applicatif
 * - Rechercher une mep-ssi : /calendrier/mep-ssi/recherche
 * - Liste des mep-ssi : /calendrier/mep-ssi/liste
 * - Calendrier global: filtrage => Javascript
 *
 * - Calendrier global: test affichage des bonnes données (test)
 * - Tester les exports Excels :
 */


    /**
     * Teste l'accès au calendrier globale
     * @dataProvider getAccesParRoles
     */
    public function testAffichageCalendrierGlobaleControleDesAcces(string $url, string $roles, int $statusCode, string $titrePage, string $sousTitrePage)
    {
        // Gestion de la connexion du client
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        // On récupère le kernel du client
        global $kernel;
        $kernel = $client->getKernel();

        // Effectue une requète
        $client->request(Request::METHOD_GET, $url);

        // Réalise différents tests sur la réponse obtenue (status, titre, contenu,...)
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains($titrePage);
            $this->assertSelectorTextContains('.page-header h2', $sousTitrePage);
        }
    }

    public function getAccesParRoles(): array
    {
        $dateString = (new \DateTime('now'))->format('Y-m-d');
        $typeVues = [
            '7prochainsJours'   => '/calendrier/global',
            'parJour'           => "/calendrier/global/{$dateString}/jours",
            'parSemaine'        => "/calendrier/global/{$dateString}/semaines",
            'parMois'           => "/calendrier/global/{$dateString}/mois",
            'par-90jours'       => "/calendrier/global/{$dateString}/-90jours",
            'par120jours'       => "/calendrier/global/{$dateString}/120jours",
        ];
        $statusParRoles =  [
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
        $data = [];
        $titrePage = 'Calendrier MEP SSI, GESIP';
        foreach ($typeVues as $urlLibelle => $url) {
            foreach ($statusParRoles as $statusParRoleLibelle => $statusParRole) {
                list($role, $statusCode) = $statusParRole;
                if ('/calendrier/global' === $url) {
                    $debutPeriode = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                    $finPeriode = (clone($debutPeriode))->add(new \DateInterval('P7D'));
                    $debutPeriodeString = $debutPeriode->format('d/m/Y');
                    $finPeriodeString = $finPeriode->format('d/m/Y');
                    $sousTitrePage = "sur les 7 prochains jours du {$debutPeriodeString} au {$finPeriodeString}";
                } elseif (0 !== preg_match('/\/([-]{0,1}\d+)jours$/', $url, $matches)) {
                    $nombreJours = (int)end($matches);
                    $nonSignesNombreJours = abs($nombreJours);
                    $nombreMois = round($nombreJours / 30);
                    $nonSignesNombreMois = abs($nombreMois);
                    $debutPeriode = $finPeriode = $periodeDescription = null;
                    if (0 <= $nombreJours) {
                        $periodeDescription = "dans les {$nonSignesNombreJours} prochains jours";
                        $debutPeriode = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                        $finPeriode = (clone($debutPeriode))->add(new \DateInterval("P{$nonSignesNombreJours}D"));
                    } else {
                        $periodeDescription = "sur les {$nonSignesNombreJours} derniers jours";
                        $finPeriode = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                        $debutPeriode = (clone($finPeriode))->sub(new \DateInterval("P{$nonSignesNombreJours}D"));
                    }
                    $debutPeriodeString = $debutPeriode->format('d/m/Y');
                    $finPeriodeString = $finPeriode->format('d/m/Y');
                    $sousTitrePage = "{$periodeDescription} du {$debutPeriodeString} au {$finPeriodeString}";
                } else {
                    $sousTitrePage = $titrePage;
                }
                $data[$urlLibelle . '_' . $statusParRoleLibelle] = [$url, $role, $statusCode, $titrePage, $sousTitrePage];
            }
        }
        return $data;
    }
}
