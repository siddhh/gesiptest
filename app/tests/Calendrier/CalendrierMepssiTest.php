<?php

namespace App\Tests\Calendrier;

use App\Entity\MepSsi;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CalendrierMepssiTest extends UserWebTestCase
{
    /**
     * Teste l'accès au calendrier des Mep-Ssi
     * @dataProvider getAccesParRoles
     */
    public function testAffichageListeMepssiControleDesAcces(string $roles, int $statusCode)
    {
        // Gestion de la connexion du client
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        // Effectue une requète
        $crawler = $client->request(Request::METHOD_GET, '/calendrier/mep-ssi/liste');

        // Réalise différents tests sur la réponse obtenue (status, titre, contenu,...)
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Consultation / Administration MEP SSI');
            $this->assertSelectorTextContains('.page-header h2', 'MEP prévisionnelles SSI - Compléments des GESIP');
            // Vérifie le nombre de résultats retournés
            $userTimeZone = new \DateTimeZone('Europe/Paris');
            $date = new \DateTime('now', $userTimeZone);
            $dateDebut = (clone $date)->modify('first day of this month')->setTime(0, 0, 0);
            $dateFin = (clone $date)->modify('last day of this month')->setTime(23, 59, 59, 59);
            $returnedMepSsiCount = $crawler->filter('table.calendrier-type-table tbody tr')->count();
            $expectedMepSsiCount = static::getEm()->getRepository(MepSsi::class)->createQueryBuilder('m')
                ->select(['COUNT(m.id)'])
                ->join('m.statut', 'ms')
                ->andWhere('m.mes BETWEEN (:dateDebut) AND (:dateFin)
                    OR (m.lep IS NOT NULL AND m.lep BETWEEN (:dateDebut) AND (:dateFin))
                    OR (m.mepDebut IS NOT NULL AND m.mepFin IS NOT NULL AND m.mepDebut < (:dateFin) AND m.mepFin > (:dateDebut))
                    OR (m.mepDebut IS NULL AND m.mepFin BETWEEN (:dateDebut) AND (:dateFin))
                    OR (m.mepFin IS NULL AND m.mepDebut BETWEEN (:dateDebut) AND (:dateFin))')
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin)
                ->join('m.statut', 'sm')
                ->join('m.equipe', 'e')
                ->getQuery()
                ->getSingleScalarResult();
            $this->assertEquals($expectedMepSsiCount, $returnedMepSsiCount);
        }
    }

    /**
     * Teste l'accès à la recherche des Mep-Ssi
     * @dataProvider getAccesParRoles
     */
    public function testAffichageRechercheMepssiControleDesAcces(string $roles, int $statusCode)
    {
        // Gestion de la connexion du client
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        // Effectue une requète
        $userTimeZone = new \DateTimeZone('Europe/Paris');
        $debutPeriode = (new \DateTime('now', $userTimeZone))->sub(new \DateInterval('P' . rand(7, 60) . 'D'));
        $finPeriode = (new \DateTime('now', $userTimeZone))->add(new \DateInterval('P' . rand(7, 60) . 'D'));
        $client->request(Request::METHOD_GET, "/calendrier/mep-ssi/recherche/{$debutPeriode->format('Y-m-d')}/{$finPeriode->format('Y-m-d')}");

        // Réalise différents tests sur la réponse obtenue (status, titre, contenu,...)
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            // Vérifie les principaux titres
            $baseTitre = 'Consulter le calendrier des interventions et des MEP SSI';
            $this->assertPageTitleContains($baseTitre . ' | Gesip');
            $this->assertSelectorTextContains('.page-header h2', $baseTitre);
            $this->assertInputValueSame('recherche_mep_ssi[periodeDebut]', $debutPeriode->format('d/m/Y'));
            $this->assertInputValueSame('recherche_mep_ssi[periodeFin]', $finPeriode->format('d/m/Y'));
        }
    }


    /**
     * Teste l'accès aux exports (xlsx et pdf) de recherche des Mep-Ssi
     * @dataProvider getAccesParRoles
     */
    public function testExportsRechercheMepssiControleDesAcces(string $roles, int $statusCode)
    {
        // Gestion de la connexion du client
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }

        // Effectue une requète pour chaque type d'export
        $debutPeriode = (new \DateTime('now'))->sub(new \DateInterval('P' . rand(7, 60) . 'D'));
        $finPeriode = (new \DateTime('now'))->add(new \DateInterval('P' . rand(7, 60) . 'D'));
        foreach (['xlsx', 'pdf'] as $exportType) {
            $client->request(Request::METHOD_HEAD, "/calendrier/mep-ssi/recherche/{$debutPeriode->format('Y-m-d')}/{$finPeriode->format('Y-m-d')}/{$exportType}");
            // Réalise différents tests sur les entetes renvoyées par le serveur
            $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
            if ($statusCode === 200) {
                $this->assertResponseHeaderSame('Content-Type', 'xlsx' === $exportType ? 'application/vnd.ms-excel' : 'application/pdf');
                $contentDisposition = 'xlsx' === $exportType
                    ? "attachment;filename=\"export_{$debutPeriode->format('Ymd')}_{$finPeriode->format('Ymd')}.{$exportType}\""
                    : "attachment; filename=export_{$debutPeriode->format('Ymd')}_{$finPeriode->format('Ymd')}.{$exportType}";
                $this->assertResponseHeaderSame('Content-Disposition', $contentDisposition);
            }
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
                200
            ],
            Service::ROLE_INVITE => [
                Service::ROLE_INVITE,
                200
            ]
        ];
    }
}
