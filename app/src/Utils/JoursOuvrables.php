<?php

namespace App\Utils;

use DateInterval;
use DateTime;

class JoursOuvrables
{
    /**
     * Fonction permettant de récupèrer la liste des jours fériés par rapport à une année précise.
     *
     * @param integer $annee
     * @return array
     */
    public static function getJoursFeries(int $annee) : array
    {
        // On récupère la date de Paques
        $paques = date('Y/m/d', easter_date($annee));

        // On renvoi notre tableau des jours fériés
        return [
            '1er janvier' =>
                (new DateTime($annee . '-1-1'))->format('d/m/Y'),
            'Lundi de pâques' =>
                (new DateTime($paques))->add(new DateInterval('P2D'))->format('d/m/Y'),
            '1er mai' =>
                (new DateTime($annee . '-5-1'))->format('d/m/Y'),
            '8 mai' =>
                (new DateTime($annee . '-5-8'))->format('d/m/Y'),
            'Ascension' =>
                (new DateTime($paques))->add(new DateInterval('P40D'))->format('d/m/Y'),
            'Lundi de pentecôte' =>
                (new DateTime($paques))->add(new DateInterval('P51D'))->format('d/m/Y'),
            '14 juillet' =>
                (new DateTime($annee . '-7-14'))->format('d/m/Y'),
            'Assomption' =>
                (new DateTime($annee . '-8-15'))->format('d/m/Y'),
            'Toussaint' =>
                (new DateTime($annee . '-11-1'))->format('d/m/Y'),
            '11 novembre' =>
                (new DateTime($annee . '-11-11'))->format('d/m/Y'),
            'Jour de Noël' =>
                (new DateTime($annee . '-12-25'))->format('d/m/Y'),
        ];
    }

    /**
     * Fonction permettant de renvoyé "true" si la date passé en paramètre tombe un jour férié (FR).
     *
     * @param DateTime $date
     * @return boolean
     */
    public static function estJourFerie(DateTime $date) : bool
    {
        return in_array($date->format('d/m/Y'), self::getJoursFeries($date->format('Y')));
    }

    /**
     * Fonction permettant de retourner le jour ouvré le plus proche dans
     *  le passé de la date passée en paramètre.
     *
     * @param DateTime $date
     * @return DateTime
     */
    public static function calculer(DateTime $date) : DateTime
    {
        // Si la date passé tombe un jour férié ou pendant un week-end,
        //  alors on traite l'information
        if (self::estJourFerie($date) || $date->format('N') >= 6) {
            // Si la date tombe un jour férié
            if (self::estJourFerie($date)) {
                // alors on recule de 1 jour
                $date->sub(new DateInterval('P1D'));
            }

            // Si la date de retard (J-2) est un samedi
            if ($date->format('N') == 6) {
                // alors on recule de 1 jour
                $date->sub(new DateInterval('P1D'));
            }

            // Si la date de retard (J-2) est un dimanche
            if ($date->format('N') == 7) {
                // alors on recule de 2 jours
                $date->sub(new DateInterval('P2D'));
            }

            // On repasse dans la fonction
            return self::calculer($date);
        }

        // Si la date est bien un jour ouvré, on la retourne
        return $date;
    }
}
