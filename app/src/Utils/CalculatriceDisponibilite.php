<?php

namespace App\Utils;

use App\Entity\Composant\PlageUtilisateur;

class CalculatriceDisponibilite
{
    /** @var array */
    private $planningDisponibilite;
    /** @var int */
    private $disponibiliteTheorique;
    /** @var \DateTime */
    private $periodeDebut;
    /** @var \DateTime */
    private $periodeFin;

    /**
     * Constructeur du calculateur de disponibilité.
     *
     * @param \DateTime             $periodeDebut
     * @param \DateTime             $periodeFin
     * @param PlageUtilisateur[]    $plagesUtilisateurs
     */
    public function __construct(\DateTime $periodeDebut, \DateTime $periodeFin, array $plagesUtilisateurs)
    {
        // On initialise notre planning de disponibilité
        $this->periodeDebut = $periodeDebut;
        $this->periodeFin = $periodeFin;
        $this->planningDisponibilite = [];
        $this->disponibiliteTheorique = 0;

        // On formatte les plages utilisateurs pour convenir pour la suite
        $plagesUtilisateursArray = [];
        foreach ($plagesUtilisateurs as $plage) {
            $plagesUtilisateursArray[$plage->getJour()][] = [
                'debut' => $plage->getDebut(),
                'fin' => $plage->getFin(),
                'minutes' => $plage->getTempsTotalEnMinutes(),
            ];
        }

        // On converti nos dates avec la timezone Europe/Paris (si pas déjà à ce format là) afin d'avoir
        //  la même référence partout
        $tz = new \DateTimeZone('Europe/Paris');
        if ($periodeDebut->getTimezone()->getName() !== $tz->getName()) {
            $periodeDebut = new \DateTime($periodeDebut->format('Y-m-d 00:00:00'), $tz);
        }
        if ($periodeFin->getTimezone()->getName() !== $tz->getName()) {
            $periodeFin = new \DateTime($periodeFin->format('Y-m-d 00:00:00'), $tz);
        }

        // On calcul le nombre de jours dans notre période
        $calculDebut = strtotime($periodeDebut->format('Y-m-d'));
        $calculFin = strtotime($periodeFin->format('Y-m-d'));
        $jours = ceil(abs($calculFin - $calculDebut) / 86400);

        // On parcourt les jours de notre période
        for ($i = 0; $i <= $jours; $i++) {
            $date = (clone $periodeDebut)->add(new \DateInterval('P'.$i.'D'));
            $this->planningDisponibilite[$date->format('Y-m-d')] = [];
            if (isset($plagesUtilisateursArray[$date->format('N')])) {
                $this->planningDisponibilite[$date->format('Y-m-d')] = array_map(function ($plage) use ($date) {
                    $plage['debut'] = (clone $date)
                        ->setTime($plage['debut']->format('H'), $plage['debut']->format('i'), $plage['debut']->format('s'));
                    $plage['fin'] = (clone $date)
                        ->setTime($plage['fin']->format('H'), $plage['fin']->format('i'), $plage['fin']->format('s'));

                    if ($plage['fin']->format('H') == 0 && $plage['fin']->format('i') == 0) {
                        $plage['fin'] = (clone $date)->setTime(0, 0, 0)->add(new \DateInterval('P1D'));
                    }

                    // Réécrit la durée de la période pour prendre en compte les changements d'heures
                    $diff = date_diff($plage['fin'], $plage['debut']);
                    $plage['minutes'] = ($diff->format('%d') * 24 * 60) + ($diff->format('%h') * 60) + $diff->format('%i');
                    $this->disponibiliteTheorique += $plage['minutes'];
                    return $plage;
                }, $plagesUtilisateursArray[$date->format('N')]);
            }
        }
    }

    /**
     * Permet d'indiquer une indisponibilité.
     *
     * @param \DateTime $debut
     * @param \DateTime $fin
     */
    public function ajoutIndisponibilite(\DateTime $debut, \DateTime $fin)
    {
        // On initialise un tableau permettant de stocker les périodes d'indispo par jours
        $indisponibilitesPeriodes = [];

        // On converti nos dates avec la timezone Europe/Paris (si pas déjà à ce format là) afin d'avoir
        //  la même référence partout
        $tz = new \DateTimeZone('Europe/Paris');
        if ($debut->getTimezone()->getName() !== $tz->getName()) {
            $debut = (clone $debut)->setTimezone($tz);
        }
        if ($fin->getTimezone()->getName() !== $tz->getName()) {
            $fin = (clone $fin)->setTimezone($tz);
        }

        // Combien de jour entre le début et la fin passée en paramètre ?
        $calculDebut = (clone $debut)->setTime(0, 0, 0);
        $calculFin = (clone $fin)->setTime(0, 0, 0);
        $joursIndispo = (int) $calculDebut->diff($calculFin)->days;

        // Si plus de 1 jour de différence
        if ($joursIndispo > 0) {
            // On boucle afin d'ajouter les périodes de jour à traiter
            for ($i = 0; $i <= $joursIndispo; $i++) {
                // On se met à la bonne date (on ajoute X jours par rapport à la date de début qui est un repère ici)
                $date = (clone $debut)->add(new \DateInterval('P' . $i . 'D'));

                // Si c'est le premier jour, alors la période est du début à 24:00:00
                if ($i == 0) {
                    $jourDebut = $date;
                    $jourFin = (clone $date)->setTime(0, 0, 0)->add(new \DateInterval('P1D'));

                // Si c'est le dernier jour, alors la période est de 00:00:00 à la fin
                } elseif ($i == $joursIndispo) {
                    $jourDebut = (clone $date)->setTime(0, 0, 0);
                    $jourFin = $fin;

                // Sinon la période est de 00:00:00 à 24:00:00
                } else {
                    $jourDebut = (clone $date)->setTime(0, 0, 0);
                    $jourFin = (clone $date)->setTime(0, 0, 0)->add(new \DateInterval('P1D'));
                }

                // On ajoute la période dans le tableau
                $indisponibilitesPeriodes[] = [
                    'debut' => $jourDebut,
                    'fin' => $jourFin
                ];
            }

        // Sinon on ajoute dans le tableau l'indisponibilité directement
        } else {
            $indisponibilitesPeriodes[] = [
                'debut' => $debut,
                'fin' => $fin
            ];
        }

        // On parcours le tableau d'indisponibilités
        foreach ($indisponibilitesPeriodes as $indisponibilite) {
            // On chope la date de la période ainsi que le début et la fin
            $dateIndisponibilite = $indisponibilite['debut']->format('Y-m-d');
            $fin = $indisponibilite['fin'];
            $debut = $indisponibilite['debut'];

            // Si une disponibilité existe pour ce jour là
            if (isset($this->planningDisponibilite[$dateIndisponibilite])) {
                // On parcourt les disponibilité de ce jour là
                foreach ($this->planningDisponibilite[$dateIndisponibilite] as $idPlage => $plage) {
                    // On récupère les plage de début et de fin de la disponibilité
                    $disponibiliteDebut = $plage['debut'];
                    $disponibiliteFin = $plage['fin'];

                    // 0) Si l'indispo dure plus longtemps que la plage utilisation
                    if ($debut <= $disponibiliteDebut && $fin >= $disponibiliteFin) {
                        unset($this->planningDisponibilite[$dateIndisponibilite][$idPlage]);


                    // 1) Si l'indispo commence avant la plage mais termine dans la plage
                    } elseif ($debut <= $disponibiliteDebut && $fin > $disponibiliteDebut && $fin < $disponibiliteFin) {
                        $this->planningDisponibilite[$dateIndisponibilite][$idPlage]['debut'] = $fin;

                    // 2) Si l'indispo commence et termine pendant la plage
                    } elseif ($debut > $disponibiliteDebut && $fin < $disponibiliteFin) {
                        $this->planningDisponibilite[$dateIndisponibilite][$idPlage]['fin'] = $debut;
                        $this->planningDisponibilite[$dateIndisponibilite][] = [
                            'debut' => $fin,
                            'fin' => $disponibiliteFin
                        ];

                    // 3) Si l'indispo commence pendant la plage et se termine après la plage
                    } elseif ($debut > $disponibiliteDebut && $debut < $disponibiliteFin && $fin >= $disponibiliteFin) {
                         $this->planningDisponibilite[$dateIndisponibilite][$idPlage]['fin'] = $debut;
                    }
                }
            }
        }
    }

    /**
     * Fonction permettant de retourner le planning des disponibilitée.
     * @return array
     */
    public function getPlanningsDisponibilite() : array
    {
        return $this->planningDisponibilite;
    }

    /**
     * Fonction permettant de retourner la durée de disponibilité réelle en minutes.
     * @return int
     */
    public function getDureeDisponibiliteReelleMinutes(): int
    {
        // On initialise notre variable
        $disponibliteReelle = 0;

        // On parcourt les disponibilités actuelles
        foreach ($this->planningDisponibilite as $jour) {
            // Si il y a des plages de disponibilités pour ce jour précis
            if ($jour) {
                // On les parcourt
                foreach ($jour as $plage) {
                    // On calcule la différence entre les deux dates afin de pouvoir en tirer un interval
                    $diff = date_diff($plage['debut'], $plage['fin']);
                    // On ajoute ensuite le nombre d'heures X 60 + le nombre de minute de différence
                    $disponibliteReelle += ($diff->format('%d') * 24 * 60) + ($diff->format('%h') * 60) + $diff->format('%i');
                }
            }
        }

        // On renvoi la disponiblité réelle
        return $disponibliteReelle;
    }

    /**
     * Fonction permettant de retourner la durée d'ouverture thoérique en minutes.
     *
     * @return int
     */
    public function getDureeDisponibiliteTheoriqueMinutes(): int
    {
        return $this->disponibiliteTheorique;
    }

    /**
     * Fonction permettant de retourner la durée d'indisponibilité en minutes.
     *
     * @return int
     */
    public function getDureeIndisponibiliteRelleMinutes(): int
    {
        return $this->getDureeDisponibiliteTheoriqueMinutes() - $this->getDureeDisponibiliteReelleMinutes();
    }

    /**
     * Fonction permettant de calculer le taux de disponibilité sur la période.
     *
     * @return float
     */
    public function getTauxDisponibilite(): float
    {
        if ($this->getDureeDisponibiliteTheoriqueMinutes() === 0) {
            return 100;
        }

        return round(($this->getDureeDisponibiliteReelleMinutes() / $this->getDureeDisponibiliteTheoriqueMinutes()) * 100, 2);
    }

    /**
     * Fonction permettant de calculer le taux de d'indisponibilité sur la période.
     *
     * @return float
     */
    public function getTauxIndisponibilite(): float
    {
        return round(100 - $this->getTauxDisponibilite(), 2);
    }
}
