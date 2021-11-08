<?php

namespace App\Utils;

abstract class ChaineDeCaracteres
{
    /**
     * Remplace la premiere lettre des prenoms
     *
     * @param string $entre
     * @return string
     */
    public static function mbUcwords(string $entre) : string
    {
        $liste = explode("-", $entre);
        foreach ($liste as &$value) {
            $strlen = mb_strlen($value);
            $firstChar = mb_substr($value, 0, 1);
            $then = mb_substr($value, 1, $strlen - 1);
            $value = mb_strtoupper($firstChar) . $then;
        }
        return implode("-", $liste);
    }

    /**
     * Nom court - exemple - J.C. NAZARETH
     *
     * @param string $prenom
     * @param string $nom
     * @return string
     */
    public static function prenomNomAbrege(string $prenom, string $nom) : string
    {
        $listprenom = explode("-", $prenom);
        foreach ($listprenom as &$value) {
            $firstChar = mb_substr($value, 0, 1);
            $value = mb_strtoupper($firstChar) . ".";
        }
        $nom = strtoupper($nom);
        return implode("", $listprenom) . " " . $nom;
    }

    /**
     * Suppression des accents
     *
     * @param string $chaine
     * @return string
     */
    public static function suppressionAccents(string $chaine) : string
    {
        return str_replace(
            array(
                'à', 'â', 'ä', 'á', 'ã', 'å',
                'î', 'ï', 'ì', 'í',
                'ô', 'ö', 'ò', 'ó', 'õ', 'ø',
                'ù', 'û', 'ü', 'ú',
                'é', 'è', 'ê', 'ë',
                'ç', 'ÿ', 'ñ',
                'À', 'Â', 'Ä', 'Á', 'Ã', 'Å',
                'Î', 'Ï', 'Ì', 'Í',
                'Ô', 'Ö', 'Ò', 'Ó', 'Õ', 'Ø',
                'Ù', 'Û', 'Ü', 'Ú',
                'É', 'È', 'Ê', 'Ë',
                'Ç', 'Ÿ', 'Ñ',
            ),
            array(
                'a', 'a', 'a', 'a', 'a', 'a',
                'i', 'i', 'i', 'i',
                'o', 'o', 'o', 'o', 'o', 'o',
                'u', 'u', 'u', 'u',
                'e', 'e', 'e', 'e',
                'c', 'y', 'n',
                'A', 'A', 'A', 'A', 'A', 'A',
                'I', 'I', 'I', 'I',
                'O', 'O', 'O', 'O', 'O', 'O',
                'U', 'U', 'U', 'U',
                'E', 'E', 'E', 'E',
                'C', 'Y', 'N',
            ),
            $chaine
        );
    }

    /**
     * Permet de retourner la valeur $minutes sous la forme XXjXXhXXm
     * @param int $minutes
     * @return string
     */
    public static function minutesEnLectureHumaine(int $minutes): string
    {
        // On calcule la différence entre 0 et notre nombre de minutes
        $zero = new \DateTime('@0');
        $offset = new \DateTime('@' . $minutes * 60);
        $diff = $zero->diff($offset);

        // On met en forme notre affichage
        $out = [];

        // Si il y a au moins un jour de différence alors on l'affiche
        $diffJours = $diff->format('%a');
        if ($diffJours > 0) {
            $out[] = $diffJours . 'j';
        }

        // Si il y a au moins une heure de différence alors on l'affiche
        $diffHeures = $diff->format('%h');
        if ($diffHeures > 0) {
            $out[] = $diffHeures.'h';
        }

        // Si il y a au moins une minute de différence alors on l'affiche
        $diffMinutes = $diff->format('%i');
        if ($diffMinutes > 0) {
            $out[] = $diffMinutes.'m';
        }

        // On retourne le résultat ou "0" si pas de résultat
        return count($out) ? implode(' ', $out) : '0';
    }

    /**
     * Fonction retournant une période de façon humaine : "Du XX/XX au XX/XX/XXXX" ou "Du XX au XX/XX/XX" ou etc...
     * en fonction de la période passée en paramètre. On peut afficher les années ou non.
     *
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     * @param bool      $affichageAnnee
     * @param string    $formatString
     *
     * @return string
     */
    public static function periodesEnLectureHumaine(\DateTime $periodeDebut, \DateTime $periodeFin, bool $affichageAnnee = true, string $formatString = 'Du %s au %s') : string
    {
        // On initisalise quelques variables pour préparer notre affichage
        $premierTerme = null;
        $dernierTerme = $affichageAnnee ? $periodeFin->format('d/m/Y') : $periodeFin->format('d/m');

        // On traite les différents cas
        if ($periodeDebut->format('Y') !== $periodeFin->format('Y')) {
            $premierTerme = $affichageAnnee ? $periodeDebut->format('d/m/Y') : $periodeDebut->format('d/m');
        } elseif ($periodeDebut->format('m') !== $periodeFin->format('m')) {
            $premierTerme = $periodeDebut->format('d/m');
        } else {
            $premierTerme = $periodeDebut->format('d');
        }

        // On retourne la chaine de caractère formatée
        return sprintf($formatString, $premierTerme, $dernierTerme);
    }

    /**
     * Fonction permettant de formatter des minutes en format HH:MM:SS.
     *
     * @param int $minutes
     * @return string
     */
    public static function minutesEnLectureHumaineSimple(int $minutes) : string
    {
        $heures = floor($minutes / 60);
        $minutes = $minutes % 60;

        $result = [];
        $result[] = str_pad($heures, 2, '0', STR_PAD_LEFT);
        $result[] = str_pad($minutes, 2, '0', STR_PAD_LEFT);
        $result[] = '00';

        return implode(':', $result);
    }
}
