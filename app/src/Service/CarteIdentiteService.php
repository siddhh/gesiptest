<?php

namespace App\Service;

use App\Entity\CarteIdentite;
use App\Entity\CarteIdentiteBase;
use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\Service;
use App\Entity\References\ListeDiffusionSi2a;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Security;

class CarteIdentiteService
{

    /**
     * Libellés des services destinataires des transmissions
     */
    const LABEL_SERVICE_SERIVE_MANAGER = 'Service Manager';
    const LABEL_SERVICE_SWITCH = 'Switch';
    const LABEL_SERVICE_SINAPS = 'Sinaps';

    /**
     * Paramètrage de la génération du hash
     */
    const HASH_ALGO = 'sha256';
    const HASH_PREFIXE = '$Gesip2020';
    const HASH_SUFFIXE = '0202piseG£';

    /**
     * Taille maximum pour le fichier des cartes d'identité en Mo.
     */
    const TAILLEMAXIMUMFICHIER = 10;

    /**
     * Liste des mimetypes acceptés pour le fichier de la carte d'identité
     */
    private static $mimeTypes = [
        '.ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        '.txt'  => 'text/plain',
    ];

    /**
     * Constructeur avec injection de dépendance pour récupérer les parametres et l'entity manager
     */

    /* @var ParameterBagInterface $params */
    private $params;
    /* @var EntityManagerInterface $em */
    private $em;
    /* @var MailerInterface $mailer */
    private $mailer;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, MailerInterface $mailer, Security $security)
    {
        $this->params = $params;
        $this->em = $em;
        $this->mailer = $mailer;
        $this->security = $security;
    }

    /**
     * Retourne la liste des mimeTypes autorisés pour le fichier de la carte d'identité
     * @return array
     */
    public static function mimeTypesAutorises(): array
    {
        return array_values(self::$mimeTypes);
    }


    /**
     * Retourne la liste des extentions autorisées pour le fichier de la carte d'identité
     * @return array
     */
    public static function extensionsAutorisees(): array
    {
        return array_keys(self::$mimeTypes);
    }

    /**
     * Retourne la taille maximale autorisée pour les cartes d'identité
     * @return array
     */
    public static function getTailleMaximumFichier(): int
    {
        return self::TAILLEMAXIMUMFICHIER;
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
        } while (null !== ($this->em->getRepository(CarteIdentite::class)->findOneBy(['nomFichier' => $hash]))
                && null !== ($this->em->getRepository(ModeleCarteIdentite::class)->findOneBy(['nomFichier' => $hash])));
        return $hash;
    }

    /**
     * Récupère les métas data et déplace le fichier uploadé dans le bon répertoire
     * @param CarteIdentiteBase $carteIdentite
     * @param UploadedFile $uploadedFile
     * @return CarteIdentite
     */
    public function enregistre(CarteIdentiteBase $carteIdentite, UploadedFile $uploadedFile): CarteIdentiteBase
    {
        // Récupération du chemin du répertoire ou on dépose le fichiers des cartes d'identité
        $carteIdentiteRepertoire = $this->params->get('carte_identite_directory');
        // affecte les propriétés du fichier
        $hash = $this->getNewHash();
        $carteIdentite->setNomFichier($hash);
        $carteIdentite->setTailleFichier($uploadedFile->getSize());
        // déplace le fichier recu dans le répertoire prévu à cet effet
        $uploadedFile->move($carteIdentiteRepertoire, $hash);
        // retourne l'objet fichier avec ses propriétés mises à jour
        return $carteIdentite;
    }

    /**
     * Construit et retourne une réponse
     * @param CarteIdentite $carteIdentite la carte d'identité devant être téléchargée
     * @return BinaryFileResponse la réponse contenant le fichier de la carte d'identité
     * @throws NotFoundHttpException si on ne parvient pas lire le fichier demandé.
     */
    public function getFichierReponse(CarteIdentiteBase $carteIdentite, string $nomFichierAffiche = 'carte-identite'): BinaryFileResponse
    {
        $carteIdentiteRepertoire = $this->params->get('carte_identite_directory');
        $cheminFichier = realpath($carteIdentiteRepertoire . DIRECTORY_SEPARATOR . $carteIdentite->getNomFichier());
        if (false !== $cheminFichier || is_readable($cheminFichier)) {
            // On va chercher le type mime du fichier
            $mimeType = mime_content_type($cheminFichier);

            // Si c'est un champ text/plain, alors on met ".txt", sinon ".ods"
            switch ($mimeType) {
                default:
                    break;
                case 'text/plain':
                    $nomFichierAffiche .= '.txt';
                    break;
                case 'application/vnd.oasis.opendocument.spreadsheet':
                    $nomFichierAffiche .= '.ods';
                    break;
            }

            // Retourne la réponse sous forme de fichier binaire (stream, etag,..)
            $response = new BinaryFileResponse($cheminFichier);
            $response->headers->set('Content-Type', $mimeType);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $nomFichierAffiche
            );
            return $response;
        }

        // Déclenche une erreur 404 spécifique si le fichier indiqué par la base de données n'est pas lisible
        throw new NotFoundHttpException('Fichier carte d\'identité introuvable.');
    }

    /**
     * Supprime le fichier
     * @param CarteIdentite $carteIdentite la carte d'identité devant être téléchargée
     */
    public function supprime(CarteIdentiteBase $carteIdentite)
    {
        $carteIdentiteRepertoire = $this->params->get('carte_identite_directory');
        $cheminFichier = realpath($carteIdentiteRepertoire . DIRECTORY_SEPARATOR . $carteIdentite->getNomFichier());
        if (is_file($cheminFichier)) {
            unlink($cheminFichier);
        }
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param CarteIdentite $carteIdentite
     * @param Service[] $transmissionServices
     * @param Composant $composant
     * @return void
     */
    public function envoyerMail(CarteIdentite $carteIdentite, array $transmissionServices = null): void
    {
        // On récupère le composant en fonction de si il s'agit d'une carte d'identité associée à un composant Gesip ou non
        $composant = $carteIdentite->getComposant();
        if (null === $composant) {
            $composant = $carteIdentite->getComposantCarteIdentite();
        }
        $isTransmission = false;

        // Construction de la liste des destinataires en fonction du contexte
        $addressDestinataires = [];
        if (null !== $transmissionServices) {
            $isTransmission = true;
            $addressDestinataires = $this->getDestinataires($transmissionServices);
        } else {
            $adminServices = $this->em->getRepository(Service::class)->getServicesParRoles([Service::ROLE_ADMIN]);
            if (in_array($this->security->getUser()->getLabel(), self::getTransmissionServiceLabels())) {
                $addressDestinataires = $this->getDestinataires($adminServices);
            } else {
                $piloteServices = $this->em->getRepository(Service::class)->getPilotageEquipes();
                if ($composant instanceof Composant) {
                    $pilotes = [$composant->getPilote(), $composant->getPiloteSuppleant()];
                    $addressDestinataires = $this->getDestinataires($adminServices, $piloteServices, $pilotes);
                } else {
                    $si2as = $this->getSI2ATransmissionMembres();
                    $addressDestinataires = $this->getDestinataires($adminServices, $piloteServices, $si2as);
                }
            }
        }

        // Définition du titre en fonction du contexte
        $labelComposant = $composant->getLabel();
        $emailMessage = (new TemplatedEmail())->from(new Address($this->params->get('noreply_mail'), $this->params->get('noreply_mail_label')));
        if ($isTransmission) {
            $emailMessage->subject("Création / Modification de la carte d'identité du composant {$labelComposant} - Gesip");
        } else {
            $emailMessage->subject("Mise à jour de la carte d’identité {$labelComposant} - Gesip");
        }

        // Ajout des destinataires au mail
        foreach ($addressDestinataires as $address) {
            $emailMessage->addTo($address);
        }

        // Envoi du mail
        $composantData = [];
        if ($composant instanceof Composant) {
            $composantData = [
                'type'  => 'composant',
                'id'    => $composant->getId(),
                'label' => $composant->getLabel(),
            ];
        } else {
            $composantData = [
                'type'  => 'identite',
                'id'    => $composant->getId(),
                'label' => $composant->getLabel() . '(non Gesip)',
            ];
        }
        $emailMessage->htmlTemplate('emails/carte-identite/transmission.html.twig');
        $emailMessage->textTemplate('emails/carte-identite/transmission.text.twig');
        $emailMessage->context([
            'composant'                 => $composantData,
            'service'                   => $carteIdentite->getService(),
            'isTransmission'            => $isTransmission,
        ]);
        $this->mailer->send($emailMessage);
    }

    /**
     * Agrege et dédoublonne des tableaux de destinataires
     *  (note: on peut passer une infinité de tableaux de destinataires en paramètre)
     * @return array
     */
    private function getDestinataires(): array
    {
        $destinataires = [];
        foreach (func_get_args() as $dests) {
            foreach ($dests as $destinataire) {
                if ($destinataire instanceof Pilote) {
                    $addresslabel = $destinataire->getNomCompletCourt();
                    $addressMail = $destinataire->getBalp();
                    $destinataires[$addressMail] = new Address($addressMail, $addresslabel);
                } elseif ($destinataire instanceof Service) {
                    $addresslabel = $destinataire->getLabel();
                    $addressMail = $destinataire->getEmail();
                    $destinataires[$addressMail] = new Address($addressMail, $addresslabel);
                }
            }
        }
        return $destinataires;
    }

    /**
     * Retourne les libellés des services pour la transmission
     * @return array
     */
    public static function getTransmissionServiceLabels(): array
    {
        return [
            self::LABEL_SERVICE_SERIVE_MANAGER,
            self::LABEL_SERVICE_SWITCH,
            self::LABEL_SERVICE_SINAPS,
        ];
    }

    /**
     * Retourne les membres du référentiel SI2A concernés par la transmission d'un nouveau composant (pas encore dans Gesip)
     * @return array
     */
    private function getSI2ATransmissionMembres(): array
    {
        return $this->em->getRepository(ListeDiffusionSi2a::class)
            ->createQueryBuilder('si2a')
            ->where('si2a.supprimeLe IS NULL')
            ->andWhere('si2a.fonction IN(:fonctions)')
            ->setParameter('fonctions', ['Adjoint DME', 'Responsable Equipe CS1', 'Responsable Equipe CS2'])
            ->getQuery()
            ->getResult();
    }
}
