<?php

namespace App\Tests\Gestion;

use App\Entity\Documentation\Document;
use App\Entity\Service;
use App\Tests\UserWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Faker;

class DocumentsTest extends UserWebTestCase
{

    /**
     * Teste d'accès à la partie gestion des documents
     * @dataProvider getAccesParRolesConsultation
     */
    public function testGestionDocumentationConsultationControleDesAcces(string $roles, int $statusCode)
    {
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }
        $client->request(Request::METHOD_GET, '/documentation/liste');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Documentation GESIP');
        }
    }

    /**
     * Teste d'accès à la partie création des documents
     * @dataProvider getAccesParRolesCreation
     */
    public function testGestionDocumentationCreationControleDesAcces(string $roles, int $statusCode)
    {
        if ($roles === "NON_CONNECTE") {
            $client = static::createClient();
        } else {
            $service = self::getOneServiceFromRole($roles);
            $client = static::createClientLoggedAs($service);
        }
        $client->request(Request::METHOD_GET, '/gestion/documentation/creer');
        $this->assertEquals($statusCode, $client->getResponse()->getStatusCode());
        if ($statusCode === 200) {
            $this->assertPageTitleContains('Créer une documentation');
        }
    }

    // Teste si on peut creer un nouveau document après s'être connecté en administrateur
    public function testCreerDocument()
    {
        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);

        //Création du fichier à joindre
        $faker = Faker\Factory::create('fr_FR');
        $testFilePath = tempnam(sys_get_temp_dir(), 'gesip_DocumentsTest_');
        $randomContent = $faker->text(256);
        file_put_contents($testFilePath, $randomContent);
        $uploadedFile = new UploadedFile($testFilePath, 'test.txt');
        $client->request(
            'POST',
            '/gestion/documentation/creer',
            [
                'document' => [
                    'titre' => 'Mon titre',
                    'description' => 'Ma description',
                    'date' => '07/01/2021',
                    'version' => '1.0',
                    'destinataires' => 'Tous les utilisateurs',
                    'fichiers' => [
                        [
                            'ordre' => '1',
                            'label' => 'Toto',
                        ]
                    ],
                    '_token' => $client->getContainer()->get('security.csrf.token_manager')->getToken('document')->getValue()
                ]
            ],
            [
                'document' => [
                    'fichiers' => [
                        [
                            'fichier' => $uploadedFile
                        ]
                    ]
                ]
            ]
        );
        $client->followRedirect();
        $this->assertPageTitleContains('Documentation GESIP');
        $documentRepository = self::getEm($client)->getRepository(Document::class);
        $dernierDocument = $documentRepository->findOneBy([], ['id' => 'DESC']);
        $this->assertEquals($dernierDocument->getTitre(), 'Mon titre');
    }

    // Teste si on peut creer un nouveau document sans fichier. Si c'est le cas on reste sur l'écran "Créer une documentation".
    public function testCreerDocumentSansFichier()
    {
        $messageErreurRecherche = 'Cette collection doit contenir 1 élément ou plus.';

        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);
        $client->request(
            'POST',
            '/gestion/documentation/creer',
            [
                'document' => [
                    'titre' => 'Mon titre',
                    'description' => 'Ma description',
                    'date' => '07/01/2021',
                    'version' => '1.0',
                    'destinataires' => 'Tous les utilisateurs',
                ]
            ]
        );
        $this->assertPageTitleContains('Créer une documentation');
        $this->assertSelectorTextContains('*', $messageErreurRecherche);
    }


    // Teste si on peut modifier un nouveau document après s'être connecté en administrateur
    public function testModifierDocument()
    {
        // connexion au compte administrateur
        $client = static::getClientByRole(Service::ROLE_ADMIN);

        // on tente de récupérer la page de modifications des documents
        $faker = Faker\Factory::create('fr_FR');
        $testFilePath = tempnam(sys_get_temp_dir(), 'gesip_DocumentsTest_');
        $randomContent = $faker->text(256);
        file_put_contents($testFilePath, $randomContent);
        $uploadedFile = new UploadedFile($testFilePath, 'test.txt');
        $client->request(
            'POST',
            "/gestion/documentation/modifier/2",
            [
                'document' => [
                    'titre' => 'Nouveau titre',
                    'description' => 'Nouvelle description',
                    'date' => '08/01/2021',
                    'version' => '2.0',
                    'destinataires' => 'Tous les référents',
                    'fichiers' => [
                        [
                            'ordre' => '1',
                            'label' => 'Titi',
                        ]
                    ],
                    '_token' => $client->getContainer()->get('security.csrf.token_manager')->getToken('document')->getValue()
                ]
            ],
            [
                'document' => [
                    'fichiers' => [
                        [
                            'fichier' => $uploadedFile
                        ]
                    ]
                ]
            ]
        );
        $client->followRedirect();
        $this->assertPageTitleContains('Modifier une documentation');
        $documentRepository = self::getEm($client)->getRepository(Document::class);
        $document = $documentRepository->findOneBy(['id' => 2]);
        $this->assertEquals($document->getTitre(), 'Nouveau titre');
    }

    public function getAccesParRolesConsultation(): array
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

    public function getAccesParRolesCreation(): array
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
                403
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
