<?php

namespace App\Tests\Interventions;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\References\MotifIntervention;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatBrouillon;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class ConsultationTest extends UserWebTestCase
{
    /**
     * ------ Fonctions privée ------
     */
    /**
     * On crée un service admin
     *
     * @return Service
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createServiceAdmin(): Service
    {
        $service = new Service();
        $service->setLabel("Service Admin");
        $service->setRoles([ Service::ROLE_ADMIN ]);
        $service->setEmail('service-admin@local.dev');
        $service->setMotdepasse('toto');
        $service->setEstPilotageDme(true);
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
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createComposant(Service $serviceExploitant): Composant
    {
        // On crée les objets annexe
        $usager = (new Usager())->setLabel('Usager');
        static::getEm()->persist($usager);
        $typeElement = (new TypeElement())->setLabel('Type element');
        static::getEm()->persist($typeElement);

        // On crée le composant
        $composant = new Composant();
        $composant->setLabel('Composant ' . uniqid());
        $composant->setUsager($usager);
        $composant->setTypeElement($typeElement);
        $composant->setIntitulePlageUtilisateur("Plage utilisateur");
        $composant->setMeteoActive(false);
        $composant->setExploitant($serviceExploitant);
        $composant->setEquipe($serviceExploitant);
        static::getEm()->persist($composant);

        // On tire la chasse
        static::getEm()->flush();
        return $composant;
    }

    /**
     * Fonction permettant de créer une demande d'intervention
     *
     * @param Composant $composant
     * @param Service   $serviceAdmin
     * @param string    $statut
     *
     * @return DemandeIntervention
     */
    private function createDemandeIntervention(Composant $composant, Service $serviceAdmin, string $statut, array $statutDonnees = []): DemandeIntervention
    {
        // On crée les objets annexes
        $motifIntervention = (new MotifIntervention())->setLabel('Motif Intervention ' . uniqid());
        static::getEm()->persist($motifIntervention);

        // On crée la demande d'intervention
        $maintenant = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $intervention = new DemandeIntervention();
        $intervention->setDemandePar($serviceAdmin);
        $intervention->setDemandeLe($maintenant);
        $intervention->setNumero($maintenant->format('YmdHis.v'));
        $intervention->setComposant($composant);
        $intervention->setDateDebut((clone $maintenant)->add(new \DateInterval('P5D')));
        $intervention->setDateFinMini((clone $maintenant)->add(new \DateInterval('P10D')));
        $intervention->setDateFinMax((clone $maintenant)->add(new \DateInterval('P11D')));
        $intervention->setStatus($statut);
        $intervention->setMotifIntervention($motifIntervention);
        $intervention->setNatureIntervention("Nature de l'intervention");
        $intervention->setPalierApplicatif(false);
        $intervention->setDescription("Description de l'intervention");
        $intervention->setDureeRetourArriere(24*60);
        $intervention->setStatusDonnees($statutDonnees);
        static::getEm()->persist($intervention);

        // On tire la chasse
        static::getEm()->flush();
        return $intervention;
    }

    /**
     * ------ Fonctions de tests ------
     */
    /**
     * Teste l'accès à la consultation d'une demande normale en fonction du rôle
     * @dataProvider getAccesConsultationDemandeRoles
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesConsultationDemandeNormale(string $role, int $statusCode)
    {
        // On crée le client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composant = $this->createComposant($serviceAdmin);
        $demande = $this->createDemandeIntervention($composant, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        if ($role !== 'NON_CONNECTE') {
            $serviceLogged = self::getOneServiceFromRole($role);
            self::loginAs($client, $serviceLogged);
        }
        $client->request(Request::METHOD_GET, '/demandes/' . $demande->getId());
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        }
    }
    public function getAccesConsultationDemandeRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      200 ]
        ];
    }

    /**
     * Teste l'accès à la consultation extérieure d'une demande normale en fonction du rôle
     * @dataProvider getAccesConsultationExterieureDemandeRoles
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesConsultationExterieureDemandeNormale(string $role, int $statusCode)
    {
        // On crée le client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composant = $this->createComposant($serviceAdmin);
        $demande = $this->createDemandeIntervention($composant, $serviceAdmin, EtatAnalyseEnCours::class);

        // On récupère la liste des demandes en brouillon
        if ($role !== 'NON_CONNECTE') {
            $serviceLogged = self::getOneServiceFromRole($role);
            self::loginAs($client, $serviceLogged);
        }
        $client->request(Request::METHOD_GET, '/demande/' . $demande->getNumero());
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        }
    }
    public function getAccesConsultationExterieureDemandeRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            200 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       200 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         200 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 200 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      200 ]
        ];
    }

    /**
     * Teste l'accès à la consultation d'une demande brouillon (et non saisie par l'utilisateur en cours) en fonction du rôle
     * @dataProvider getAccesConsultationDemandeBrouillonRoles
     * @param string $role
     * @param int    $statusCode
     */
    public function testAccesConsultationDemandeBrouillon(string $role, int $statusCode)
    {
        // On crée le client
        global $kernel;
        $client = static::createClient();
        $kernel = $client->getKernel();
        /** @var EntityManager $em */
        self::$entityManager = $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // On crée notre service admin, notre composant et 2 demandes d'interventions
        $serviceAdmin = $this->createServiceAdmin();
        $composant = $this->createComposant($serviceAdmin);
        $demande = $this->createDemandeIntervention($composant, $serviceAdmin, EtatBrouillon::class);

        // On récupère la liste des demandes en brouillon
        if ($role !== 'NON_CONNECTE') {
            $serviceLogged = self::getOneServiceFromRole($role);
            self::loginAs($client, $serviceLogged);
        }
        $client->request(Request::METHOD_GET, '/demandes/' . $demande->getId());
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertSelectorTextContains('*', $demande->getNumero());
        }
    }
    public function getAccesConsultationDemandeBrouillonRoles(): array
    {
        return [
            'NON_CONNECTE'              => [ 'NON_CONNECTE',            302 ],
            Service::ROLE_ADMIN         => [ Service::ROLE_ADMIN,       302 ],
            Service::ROLE_DME           => [ Service::ROLE_DME,         302 ],
            Service::ROLE_INTERVENANT   => [ Service::ROLE_INTERVENANT, 302 ],
            Service::ROLE_INVITE        => [ Service::ROLE_INVITE,      302 ]
        ];
    }
}
