<?php

namespace App\Twig;

use App\Entity\Service;
use App\Utils\ChaineDeCaracteres;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Ajoute de nouvelles fonctions à Twig
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('getSI2MailLink', [$this, 'getSI2MailLink']),
        ];
    }

    /**
     * retourne le lien permettant d'ouvrir le client mail local pour écrire aux équipes SI2
     */
    public function getSI2MailLink(string $subject = null): string
    {
        $recipients = [];
        foreach ($this->em->getRepository(Service::class)->getPilotageEquipes() as $si2Services) {
            $recipients[] = $si2Services->getLabel() . '<' . $si2Services->getEmail() . '>';
        }
        return 'mailto:' . implode(',', $recipients)
            . ($subject ? '?subject=' . $subject : '');
    }


    /**
     * Ajoute de nouveaux filtres à Twig
     */
    public function getFilters()
    {
        return [
            new TwigFilter('properties', [$this, 'getObjectProperties']),
            new TwigFilter('minutesToHumanReadable', [$this, 'getHumanReadableMinute']),
            new TwigFilter('octetsToHumanReadable', [$this, 'getHumanReadableOctet'])
        ];
    }

    /**
     * Retourne un tableau contenant les attributs d'un object
     */
    public function getObjectProperties($object): array
    {
        // provisionne le tableau pour commencer le tableau avec label
        $ret = [ 'label' => null ];
        $a = (array)$object;
        foreach ($a as $k => $v) {
            if (!is_object($v)) {
                $key = explode(chr(0), $k);
                $key = array_pop($key);
                $ret[$key] = $v;
            }
        }
        return $ret;
    }

    /**
     * Permet de retourner la valeur $minutes sous la forme XXjXXhXXm
     * @param int $minutes
     * @return string
     */
    public function getHumanReadableMinute(int $minutes): string
    {
        return ChaineDeCaracteres::minutesEnLectureHumaine($minutes);
    }

    /**
     * Retourne une taille fichier / mémoire lisible par un humain
     * @param int $minutes
     * @return string
     */
    public function getHumanReadableOctet(int $octets, int $round = 2): string
    {
        $unites = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $base = 0;
        while ($octets / pow(1024, $base) > 1000) {
            $base++;
        }
        return round($octets / pow(1024, $base), $round) . ' ' . $unites[$base] . '.';
    }
}
