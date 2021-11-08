<?php

namespace App\Tests;

use App\Entity\ModeleCarteIdentite;
use App\Entity\Service;
use App\Service\CarteIdentiteService;
use App\Tests\UserWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ModeleCarteIdentiteTest extends UserWebTestCase
{
    /** @var ModeleCarteIdentiteTest $carteIdentiteService */
    private $carteIdentiteService;

    /**
     * Injecte le service de gestion des cartes d'identité
     * @return void
     */
    protected function setUp()
    {
        self::bootKernel();
        $this->carteIdentiteService = self::$container->get(CarteIdentiteService::class);
        self::ensureKernelShutdown();
    }

    /**
     * Teste l'interface d'administration
     * @dataProvider getAccesAdminModeleCarteIdentite
     */
    public function testModeleCarteIdentiteControleAdmin(string $role, int $statusCode)
    {
        // Récupère un client avec le role désiré
        $client = static::getClientByRole($role);
        // Teste de télécharger un modèle alors qu'aucun est activé (on part du principe qu'il n'existe pas de modèle en début de test)
        $crawler = $client->request(Request::METHOD_GET, '/gestion/modele-carte-identite');
        $this->assertEquals($client->getResponse()->getStatusCode(), 204 === $statusCode ? 200 : $statusCode);
    }

    /**
     * Teste le service de téléchargement d'un modèle
     * @dataProvider getAccesModeleCarteIdentite
     */
    public function testModeleCarteIdentiteControleTelechargement(string $role, int $statusCode)
    {
        // Récupère un client avec le role désiré
        $client = static::getClientByRole($role);
        // Teste de télécharger un modèle alors qu'aucun est activé (on part du principe qu'il n'existe pas de modèle en début de test)
        $crawler = $client->request(Request::METHOD_GET, '/modele-carte-identite');
        $this->assertEquals($client->getResponse()->getStatusCode(), $statusCode === 200 ? 404 : $statusCode);
        // Enregistre un modèle activé en base de données au préalable
        $modeleCarteIdentite = $this->getModeleCarteIdentite($client, true);
        $modeleCarteIdentiteId = $modeleCarteIdentite->getId();
        // Télécharge le model actuellement activé
        $crawler = $client->request(Request::METHOD_GET, '/modele-carte-identite');
        $this->assertEquals($client->getResponse()->getStatusCode(), $statusCode);
    }

    /**
     * Teste le webservice d'activation d'un modèle de carte d'identité
     * @dataProvider getAccesAdminModeleCarteIdentite
     */
    public function testModeleCarteIdentiteControleActivation(string $role, int $statusCode)
    {
        // Récupère un client avec le role désiré
        $client = static::getClientByRole($role);
        // Enregistre 2 modèles en base de données au préalable (le premier non actif, le deuxième activé)
        $modeleCarteIdentite1 = $this->getModeleCarteIdentite($client, false);
        $modeleCarteIdentite1Id = $modeleCarteIdentite1->getId();
        $modeleCarteIdentite2 = $this->getModeleCarteIdentite($client, true);
        $modeleCarteIdentite2Id = $modeleCarteIdentite2->getId();
        // Appelle le webservice d'activation avec le modèle non-actif et check le status de la réponse
        $crawler = $client->request(Request::METHOD_PUT, '/ajax/modele-carte-identite/activer/' . $modeleCarteIdentite1Id);
        $this->assertEquals($client->getResponse()->getStatusCode(), $statusCode);
        // Vérifie que le modèle est supprimé en base de données
        if (204 === $statusCode) {
            $em = static::getEm($client);
            $em->clear();   // La mise à jour de l'activation ne passe pas par le manager, on le force donc à récupérer l'état des modèles directement à partir de la base de données.
            $modeleCarteIdentites = $em->getRepository(ModeleCarteIdentite::class)->findAll();
            foreach ($modeleCarteIdentites as $modeleCarteIdentite) {
                if ($modeleCarteIdentite1Id === $modeleCarteIdentite->getId()) {
                    $this->assertTrue($modeleCarteIdentite->getActif());
                } else {
                    $this->assertFalse($modeleCarteIdentite->getActif());
                }
            }
        }
    }

    /**
     * Teste le webservice de suppression de modèle de carte d'identité
     * @dataProvider getAccesAdminModeleCarteIdentite
     */
    public function testModeleCarteIdentiteControleSuppression(string $role, int $statusCode)
    {
        // Récupère un client avec le role désiré
        $client = static::getClientByRole($role);
        // Enregistre un modèle en base de données au préalable
        $modeleCarteIdentite = $this->getModeleCarteIdentite($client);
        $modeleCarteIdentiteId = $modeleCarteIdentite->getId();
        // Appelle le webservice et check le status de la réponse
        $crawler = $client->request(Request::METHOD_DELETE, '/ajax/modele-carte-identite/' . $modeleCarteIdentiteId);
        $this->assertEquals($client->getResponse()->getStatusCode(), $statusCode);
        // Vérifie que le modèle est supprimé en base de données
        if (204 === $statusCode) {
            $modeleCarteIdentite = static::getEm($client)->getRepository(ModeleCarteIdentite::class)->find($modeleCarteIdentiteId);
            $this->assertNull($modeleCarteIdentite);
        }
    }

    /**
     * Ajoute un nouveau modèle de carte d'identité en base de données
     */
    public function getModeleCarteIdentite(KernelBrowser $client, $isActif = false, $commentaire = 'Mon modele'): ModeleCarteIdentite
    {
        // Creation d'une nouvelle instance
        $modeleCarteIdentite = new ModeleCarteIdentite();
        $modeleCarteIdentite->setCommentaire($commentaire);
        $modeleCarteIdentite->setActif($isActif);
        // Enregistre un fichier
        $filePath = tempnam(sys_get_temp_dir(), 'modele-carte-identite_');
        file_put_contents($filePath, hash('sha256', microtime(true)));
        $uploadedFile = new UploadedFile($filePath, 'tmp.ods', 'application/vnd.oasis.opendocument.spreadsheet', null, true);
        $modeleCarteIdentite = $this->carteIdentiteService->enregistre($modeleCarteIdentite, $uploadedFile);
        // Enregistre la modification en base de données
        $em = static::getEm($client);
        $em->persist($modeleCarteIdentite);
        $em->flush();
        return $modeleCarteIdentite;
    }

    /**
     * Retourne la liste des rôles et des status pour les services d'administration
     * @return array
     */
    public function getAccesAdminModeleCarteIdentite(): array
    {
        $roles = [
            'NON_CONNECTE' => [
                'NON_CONNECTE',
                302
            ],
            Service::ROLE_ADMIN => [
                'ROLE_ADMIN',
                204
            ],
            Service::ROLE_DME => [
                'ROLE_DME',
                403
            ],
            Service::ROLE_INTERVENANT => [
                'ROLE_INTERVENANT',
                403
            ],
            Service::ROLE_INVITE => [
                'ROLE_INVITE',
                403
            ]
        ];
        return $roles;
    }

    /**
     * Retourne la liste des rôles et des status pour les services utilisateurs
     * @return array
     */
    public function getAccesModeleCarteIdentite(): array
    {
        $roles = [
            'NON_CONNECTE' => [
                'NON_CONNECTE',
                302
            ],
            Service::ROLE_ADMIN => [
                'ROLE_ADMIN',
                200
            ],
            Service::ROLE_DME => [
                'ROLE_DME',
                200
            ],
            Service::ROLE_INTERVENANT => [
                'ROLE_INTERVENANT',
                200
            ],
            Service::ROLE_INVITE => [
                'ROLE_INVITE',
                200
            ]
        ];
        return $roles;
    }
}
