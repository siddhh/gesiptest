<?php

namespace App\Tests\Meteo;

use App\Entity\Composant;
use App\Entity\Meteo\Evenement;
use App\Entity\Meteo\Publication;
use App\Entity\Meteo\Validation;
use App\Entity\References\ImpactMeteo;
use App\Entity\References\MotifIntervention;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Meteo\Composant as MeteoComposant;

class ConsultationTest extends UserWebTestCase
{
    /**
     * ------ Fonctions privées ------
     */
    /**
     * On crée un service intervenant
     *
     * @return Service
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createService(): Service
    {
        $service = new Service();
        $service->setLabel("Service INTERVENANT " . uniqid());
        $service->setRoles([ Service::ROLE_INTERVENANT ]);
        $service->setEmail('service' . uniqid() . '@local');
        $service->setMotdepasse('toto');
        $service->setEstServiceExploitant(true);
        static::getEm()->persist($service);
        static::getEm()->flush();
        return $service;
    }

    /**
     * On crée un composant
     *
     * @param Service $serviceExploitant
     *
     * @return Composant
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createComposant(?Service $serviceExploitant): Composant
    {
        // On crée les objets annexe
        $usager = (new Usager())->setLabel('Usager Perimetre Applicatif');
        static::getEm()->persist($usager);
        $typeElement = (new TypeElement())->setLabel('Type element Perimetre Applicatif');
        static::getEm()->persist($typeElement);

        // On crée le composant
        $composant = new Composant();
        $composant->setLabel('Composant ' . uniqid());
        $composant->setUsager($usager);
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur Perimetre Applicatif");
        $composant->setMeteoActive(true);
        $composant->setExploitant($serviceExploitant);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * On crée un évènement
     *
     * @return Evenement
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createEvenement(Composant $composant): Evenement
    {
        // On crée les objets annexe
        $impactMeteo = (new ImpactMeteo())->setLabel('Impact météo');
        static::getEm()->persist($impactMeteo);
        $motifIntervention = (new MotifIntervention())->setLabel('Motif intervention');
        static::getEm()->persist($motifIntervention);

        // On crée l'évènement
        $evenement = new Evenement();
        $evenement->setComposant($composant);
        $evenement->setImpact($impactMeteo);
        $evenement->setTypeOperation($motifIntervention);
        $evenement->setDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 12:00:00', new \DateTimeZone('Europe/Paris'))->setTimezone(new \DateTimeZone('UTC')));
        $evenement->setFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-10 14:00:00', new \DateTimeZone('Europe/Paris'))->setTimezone(new \DateTimeZone('UTC')));
        static::getEm()->persist($evenement);

        // On tire la chasse
        static::getEm()->flush();
        return $evenement;
    }

    /**
     * On crée une publication
     *
     * @return Publication
     */
    private function createPublicationMeteo(): Publication
    {
        $publicationMeteo = new Publication();
        $publicationMeteo->setPeriodeDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00', new \DateTimeZone('Europe/Paris')));
        $publicationMeteo->setPeriodeFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59', new \DateTimeZone('Europe/Paris')));
        static::getEm()->persist($publicationMeteo);
        static::getEm()->flush();
        return $publicationMeteo;
    }


    /**
     * On crée une publication
     *
     * @param Composant $composant
     * @return MeteoComposant
     */
    private function createComposantMeteo(Composant $composant): MeteoComposant
    {
        $composantMeteo = new MeteoComposant();
        $composantMeteo->setComposant($composant);
        $composantMeteo->setPeriodeDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00', new \DateTimeZone('Europe/Paris')));
        $composantMeteo->setPeriodeFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59', new \DateTimeZone('Europe/Paris')));
        $composantMeteo->setMeteo(MeteoComposant::ENSOLEILLE);
        $composantMeteo->setDisponibilite(99);
        static::getEm()->persist($composantMeteo);
        static::getEm()->flush();
        return $composantMeteo;
    }

    /**
     * On crée une validation
     *
     * @param Composant $composant
     * @return MeteoComposant
     */
    private function createValidationMeteo(Service $exploitant): Validation
    {
        $validationMeteo = new Validation();
        $validationMeteo->setPeriodeDebut(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00', new \DateTimeZone('Europe/Paris')));
        $validationMeteo->setPeriodeFin(\DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59', new \DateTimeZone('Europe/Paris')));
        $validationMeteo->setExploitant($exploitant);
        static::getEm()->persist($validationMeteo);
        static::getEm()->flush();
        return $validationMeteo;
    }


    /**
     * ------ Fonctions de tests ------
     */
    /**
     * Teste d'accès à la partie consultation de la météo
     * @dataProvider getAccesIndexRoles
     */
    public function testAccesIndexParRole(string $role, int $statusCode)
    {
        if ($role === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($role);
            $client = static::createClientLoggedAs($service);
        }

        $client->request(Request::METHOD_GET, '/meteo/consultation');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Consultation des tableaux de bord SI des composants');
            $this->assertSelectorTextContains('.page-header h2', 'Consultation des tableaux de bord SI des composants');
        }
    }
    public function getAccesIndexRoles(): array
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
     * Teste la consultation de la météo d'une période
     * @dataProvider getConsultationMeteoCas
     */
    public function testConsultationMeteo(bool $withValidation = false, bool $withComposantMeteoCache = false)
    {
        // On crée le client et on récupère l'entity manager
        $client = static::createClient();
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée quelques données dans la base
        $service = $this->createService();
        $composant = $this->createComposant($service);
        $this->createEvenement($composant);
        $this->createPublicationMeteo();
        if ($withComposantMeteoCache) {
            $this->createComposantMeteo($composant);
        }
        $validationMeteo = null;
        if ($withValidation) {
            $validationMeteo = $this->createValidationMeteo($service);
        }

        // On se connecte
        static::loginAs($client, $service);

        // On effectue une requête permettant de récupérer les composants et leurs indices / disponibilités météo associées
        $client->request(Request::METHOD_GET, '/ajax/meteo/exploitants/' . $service->getId() . '/composants/20210107?consultation=1');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $attendu = [
            'validation' => $withValidation ? $validationMeteo->getAjouteLe()->format('c'): false,
            'composants' => [
                [
                    'id' => $composant->getId(),
                    'label' => $composant->getLabel(),
                    'indice' => $withValidation ? MeteoComposant::ENSOLEILLE : MeteoComposant::NC,
                    'disponibilite' => $withComposantMeteoCache ? 99 : null,
                    'href' => '/meteo/consultation/20210107/' . $service->getId() . '?c[]=' . $composant->getId()
                ]
            ]
        ];
        $this->assertEquals($attendu, json_decode($client->getResponse()->getContent(), true));

        // On effectue une requête pour atteindre la page listant les évènements
        $client->request(Request::METHOD_GET, '/meteo/consultation/20210107/' . $service->getId(), ['c' => [ $composant->getId() ]]);
        $this->assertSelectorTextContains('.card-header', $service->getLabel());
        $this->assertSelectorTextContains('.card-title', $composant->getLabel());
        if ($withComposantMeteoCache) {
            $this->assertSelectorTextContains('#tableau-resultats', 'Impact météo');
            $this->assertSelectorTextContains('#tableau-resultats', 'Motif intervention');
            $this->assertSelectorTextContains('#tableau-resultats', 'du 07/01 12:00');
            $this->assertSelectorTextContains('#tableau-resultats', 'au 10/01 14:00');
        }
    }
    public function getConsultationMeteoCas(): array
    {
        return [
            'SANS_VALIDATION_AVEC_CACHE_AVEC_EXPOITANT' => [ false, true ],
            'AVEC_VALIDATION_AVEC_CACHE_AVEC_EXPOITANT' => [ true, true ],
            'SANS_VALIDATION_SANS_CACHE_AVEC_EXPOITANT' => [ false, false ],
            'AVEC_VALIDATION_SANS_CACHE_AVEC_EXPOITANT' => [ true, false ],
        ];
    }
}
