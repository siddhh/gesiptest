<?php

namespace App\DataFixtures\Documentation;

use App\Entity\Documentation\Document;
use App\Entity\Documentation\Fichier;
use App\Service\DocumentationService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker;

class DocumentationFixture extends Fixture
{

    /**
     * Récupère le Service de documentation
     */
    private $documentationService;

    public function __construct(DocumentationService $documentationService)
    {
        $this->documentationService = $documentationService;
    }

    /**
     * Génère des documents en base de données pour effectuer des tests
     */
    public function load(ObjectManager $manager)
    {
        // On initialise faker
        $faker = Faker\Factory::create('fr_FR');

        //on génère 3 documents
        for ($i = 0; $i < 3; $i++) {
            $document = new Document();
            $document->setTitre($faker->unique()->firstname);
            $document->setDescription($faker->text(256));
            $document->setDate(new \DateTime());
            $document->setVersion('V0');
            $destinataires = $faker->text(256);
            $document->setDestinataires($destinataires);
            ($i == 2) ? $document->setSupprimeLe(new \DateTime()) : '';

            //création pour ce document des fichiers mais en base de données uniquement
            $nombreDeFichiers = rand(1, 2);
            for ($j = 1; $j < $nombreDeFichiers; $j++) {
                $fichier = new Fichier();
                // génération d'un hash aléatoire
                $fichier->setHash($this->documentationService->getNewHash());
                $fichier->setOrdre($j);
                $titre = $faker->unique()->firstname;
                $fichier->setLabel($titre);
                $fichier->setMimeType('text/plain');
                $fichier->setExtension('txt');
                $fichier->setTaille(rand(1, 1000000));
                $fichier->setDocument($document);
                (rand(0, 4) == 4) ? $fichier->setSupprimeLe(new \DateTime()) : '';
                $manager->persist($fichier);
            }
            $manager->persist($document);
        }
        $manager->flush();
    }
}
