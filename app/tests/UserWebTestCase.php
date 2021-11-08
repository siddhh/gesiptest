<?php

namespace App\Tests;

use App\Entity\Service;
use App\Utils\Tests\TestBrowserToken;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserWebTestCase extends WebTestCase
{

    const ROLE_NON_CONNECTE = "NON_CONNECTE";

    /** @var EntityManager */
    protected static $entityManager;

    /** @var Service[] */
    private static $cacheServiceRoles = [];

    /**
     * Fonction lancée au démarage des tests de la classe
     */
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$entityManager = self::$container->get('doctrine.orm.entity_manager');
        self::ensureKernelShutdown();
    }

    /**
     * Fonction permettant de se connecter à un compte avec des rôles bien défini
     *
     * @param KernelBrowser $client
     * @param Service $service
     */
    public static function loginAs(KernelBrowser &$client, Service &$service) : void
    {
        $session = self::$container->get('session');

        $token = new TestBrowserToken($service->getRoles(), $service);
        $token->setAuthenticated(true);
        $session->set('_security_main', serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }

    /**
     * Fonction permettant de créer un client déjà connecté avec un utilisateur
     *
     * @param Service $service
     * @return KernelBrowser
     */
    public static function createClientLoggedAs(Service &$service) : KernelBrowser
    {
        $client = static::createClient();
        static::loginAs($client, $service);
        return $client;
    }

    /**
     * Fonction retournant directement un client à partir d'un role (y compris le pseudo-rôle "NON_CONNECTE")
     *
     * @param string $role
     * @return KernelBrowser
     */
    public static function getClientByRole(string $role) : KernelBrowser
    {
        $client = null;
        if ($role === self::ROLE_NON_CONNECTE) {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($role);
            $client = static::createClientLoggedAs($service);
        }
        return $client;
    }

    /**
     * Fonction permettant de récupérer l'entity manager de doctrine
     *
     * @param KernelBrowser $client
     * @return EntityManager
     */
    public static function getEm(KernelBrowser $client = null): EntityManager
    {
        if ($client instanceof KernelBrowser) {
            return $client->getContainer()->get('doctrine.orm.entity_manager');
        }
        return self::$entityManager;
    }

    /**
     * Fonction permettant de récupérer le repository associé à l'entité passée en paramètre
     *
     * @param string             $entityClass
     * @param KernelBrowser|null $client
     *
     * @return ObjectRepository
     */
    public static function getEmRepository(string $entityClass, KernelBrowser $client = null): ObjectRepository
    {
        return self::getEm($client)->getRepository($entityClass);
    }

    /**
     * Fonction permettant de récupérer un service selon les critères que l'on souhaite
     *
     * @param array $criteres
     * @return Service
     */
    public static function getOneService(array $criteres): Service
    {
        /** @var Service $service */
        $service = self::getEmRepository(Service::class)->findOneBy($criteres);
        return $service;
    }

    /**
     * Fonction permettant de récupérer un service par un rôle défini
     *
     * @param string $role
     * @return Service
     */
    public static function getOneServiceFromRole(string $role): Service
    {
        $roles = [
            Service::ROLE_ADMIN => '0 Service Administrateur',
            Service::ROLE_DME => '0 Service DME',
            Service::ROLE_INTERVENANT => '0 Service Intervenant',
            Service::ROLE_INVITE => '0 Service invité',
        ];

        if (!in_array($role, array_keys(self::$cacheServiceRoles))) {
            self::$cacheServiceRoles[$role] = self::getOneService(['label' => $roles[$role]]);
        }
        return self::$cacheServiceRoles[$role];
    }

    /**
     * On ferme proprement les ressources utilisées
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::$cacheServiceRoles = [];
        if (self::$entityManager instanceof EntityManager) {
            self::$entityManager->close();
            self::$entityManager = null;
        }
    }
}
