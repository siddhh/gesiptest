<?php

namespace App\Utils;

class I18nDate
{
    /**
     * Permet de traduire les dates en français directement, et sans passer par la locale système.
     *
     * @param string    $format
     * @param \DateTime $dateTime
     *
     * @return bool|string
     */
    public static function __(string $format, \DateTime $dateTime)
    {
        $param_D = array('', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim');
        $param_l = array('', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche');
        $param_F = array('', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre');
        $param_M = array('', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc');
        $return = '';

        for ($i = 0, $len = strlen($format); $i < $len; $i++) {
            switch ($format[$i]) {
                case '\\':
                    $i++;
                    $return .= isset($format[$i]) ? $format[$i] : '';
                    break;
                case 'D':
                    $return .= $param_D[date('N', $dateTime->getTimestamp() + $dateTime->getOffset())];
                    break;
                case 'l':
                    $return .= $param_l[date('N', $dateTime->getTimestamp() + $dateTime->getOffset())];
                    break;
                case 'F':
                    $return .= $param_F[date('n', $dateTime->getTimestamp() + $dateTime->getOffset())];
                    break;
                case 'M':
                    $return .= $param_M[date('n', $dateTime->getTimestamp() + $dateTime->getOffset())];
                    break;
                default:
                    $return .= date($format[$i], $dateTime->getTimestamp() + $dateTime->getOffset());
                    break;
            }
        }

        return $return;
    }
}
