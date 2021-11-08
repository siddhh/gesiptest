<?php

namespace App\Service;

use App\Entity\Documentation\Fichier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentationService
{

    /**
     * Paramètrage de la génération du hash
     */
    const HASH_ALGO = 'sha256';
    const HASH_PREFIXE = '$Gesip2020';
    const HASH_SUFFIXE = '0202piseG£';

    /**
     * Liste des mimetypes acceptés pour la documentation
     */
    private static $mimeTypes = [
        '.txt'  => 'text/plain',
        '.pdf'  => 'application/pdf',
        '.doc'  => 'application/msword',
        '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.odt'  => 'application/vnd.oasis.opendocument.text',
        '.xls'  => 'application/vnd.ms-excel',
        '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        '.ppt'  => 'application/vnd.ms-powerpoint',
        '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        '.pps'  => 'application/vnd.ms-pps',
        '.odp'  => 'application/vnd.oasis.opendocument.presentation',
    ];


    /**
     * Constructeur avec injection de dépendance pour récupérer les parametres et l'entity manager
     */
    private $params;
    private $em;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->params = $params;
        $this->em = $em;
    }

    /**
     * Retourne la liste des mimeTypes autorisés pour les fichiers de documentations
     * @return array
     */
    public static function mimeTypesAutorises(): array
    {
        return array_values(self::$mimeTypes);
    }


    /**
     * Retourne la liste des extentions autorisées pour les fichiers de documentations
     * @return array
     */
    public static function extensionsAutorisees(): array
    {
        return array_keys(self::$mimeTypes);
    }

    /**
     * Retourne un nouveau hash unique
     * @return string
     */
    public function getNewHash($maxTries = 3): string
    {
        $tries = 0;
        $hash = null;
        do {
            if ($tries > $maxTries) {
                throw new \Exception("Impossible de générer un hash fichier différent après {$maxTries} tentatives.");
            }
            $tries++;
            $hash = hash(self::HASH_ALGO, self::HASH_PREFIXE . microtime(true) . self::HASH_SUFFIXE);
        } while (null !== ($fichier = $this->em->getRepository(Fichier::class)->findOneBy(['hash' => $hash])));
        return $hash;
    }

    /**
     * Récupère les métas data et déplace le fichier uploadé dans le bon répertoire
     * @param Fichier $fichier
     * @param UploadedFile $uploadedFile
     * @return Fichier
     */
    public function enregistre(Fichier $fichier, UploadedFile $uploadedFile): Fichier
    {
        // Récupération du chemin du répertoire ou on dépose les fichiers de documentation
        $documentationRepertoire = $this->params->get('documentation_directory');
        // affecte les propriétés du fichier
        $hash = $this->getNewHash();
        $fichier->setHash($hash);
        $fichier->setExtension($uploadedFile->guessExtension());
        $fichier->setMimeType($uploadedFile->getMimeType());
        $fichier->setTaille($uploadedFile->getSize());
        // déplace le fichier recu dans le répertoire prévu à cet effet
        $uploadedFile->move($documentationRepertoire, $hash);
        // retourne l'objet fichier avec ses propriétés mises à jour
        return $fichier;
    }
}
