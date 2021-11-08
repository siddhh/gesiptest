<?php

namespace App\Tests;

use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\Service;
use App\Entity\References\Domaine;
use App\Entity\References\Mission;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RestitutionsTest extends UserWebTestCase
{
    /**
     * Teste d'accès aux Restitutions
     * @dataProvider getAccesRestitutions
     */
    public function testRestitutionsControleDesAcces(string $role, string $type, string $titre)
    {
        $client = static::getClientByRole($role);

        // test de la page de type Liste
        $client->request(Request::METHOD_GET, '/restitution/' . $type);
        $codeHttpAttendu = ($role == 'NON_CONNECTE' ? 302 : 200);
        $codeHttpRecu = $client->getResponse()->getStatusCode();
        $this->assertEquals($codeHttpAttendu, $codeHttpRecu);
        if ($codeHttpRecu === 200) {
            $this->assertSelectorTextContains('.page-header h2', $titre);

            // test des exports sur la page de type Liste
            $client->request(Request::METHOD_HEAD, '/restitution/' . $type . '/xlsx');
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
            $client->request(Request::METHOD_HEAD, '/restitution/' . $type . '/pdf');
            $this->assertEquals(200, $client->getResponse()->getStatusCode());

            // test de la page de type Fiche
            $id = self::getEchantillonIdRestitution($type);
            $client->request(Request::METHOD_HEAD, '/restitution/' . $type . '/' . $id);
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function getAccesRestitutions(): array
    {
        $roles = ['NON_CONNECTE', Service::ROLE_ADMIN, Service::ROLE_DME, Service::ROLE_INTERVENANT, Service::ROLE_INVITE];
        $titresParType = [
            'composants'            => 'composants',
            'esi'                   => 'ESI',
            'domaines'              => 'Domaine',
            'pilotes'               => 'Pilote',
            'equipes'               => 'Équipe',
            'bureaux-rattachement'  => 'rattachement',
            'missions'              => 'Mission',
            'services'              => 'Service'
        ];
        $data = [];
        foreach ($roles as $role) {
            foreach (array_keys($titresParType) as $type) {
                $data[] = [$role, $type, $titresParType[$type]];
            }
        }
        return $data;
    }

    private function getEchantillonIdRestitution(string $type): ?int
    {
        switch ($type) {
            case 'composants':
                $req = static::getEmRepository(Composant::class)->findBy(['archiveLe' => null]);
                return $req[array_rand($req, 1)]->getId();
            case 'esi':
                $req = static::getEmRepository(Service::class)->restitutionListingEsi();
                return $req[array_rand($req, 1)]['id'];
            case 'domaines':
                $req = static::getEmRepository(Domaine::class)->restitutionListing();
                return $req[array_rand($req, 1)]['id'];
            case 'pilotes':
                $req = static::getEmRepository(Pilote::class)->restitutionListing();
                return $req[array_rand($req, 1)]['id'];
            case 'equipes':
                $req = static::getEmRepository(Service::class)->restitutionListingEquipesPilotage();
                return $req[array_rand($req, 1)]['id'];
            case 'bureaux-rattachement':
                $req = static::getEmRepository(Service::class)->restitutionListingBureauxRattachement();
                return $req[array_rand($req, 1)]['id'];
            case 'missions':
                $req = static::getEmRepository(Mission::class)->restitutionListing();
                return $req[array_rand($req, 1)]['id'];
            case 'services':
                $req = static::getEmRepository(Service::class)->restitutionListingServices();
                return $req[array_rand($req, 1)]['id'];
            default:
                throw new NotFoundHttpException();
        }
        return null;
    }
}
