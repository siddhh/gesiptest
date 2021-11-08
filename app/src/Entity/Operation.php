<?php

namespace App\Entity;

use App\Entity\Composant\Annuaire;
use App\Entity\Demande\Impact;
use App\Utils\ChaineDeCaracteres;

/**
 * Classe Operation représentant soit une demande d'intervention soit une MEP SSI.
 * (Ceci permet notamment de pouvoir centraliser les différents éléments pour l'affichage pour le calendrier GESIP/CMEP)
 *
 * @package App\Entity
 */
class Operation
{
    /**
     * On défini quelques constantes permettant d'établir le type d'opération.
     */
    const TYPE_MEPSSI = 'mepssi';
    const TYPE_GESIP = 'gesip';

    /**
     * Représente l'objet original.
     *
     * @var DemandeIntervention|MepSsi
     */
    private $original;

    /**
     * Représente un objet impact.
     *
     * @var ?Impact
     */
    private $impactOriginal;

    /**
     * Operation constructor.
     * On passe l'object original.
     *
     * @param DemandeIntervention|MepSsi $original
     * @param Impact|null $impactOriginal
     */
    public function __construct($original, ?Impact $impactOriginal = null)
    {
        $this->original = $original;
        $this->impactOriginal = $impactOriginal;
    }

    /**
     * On renvoi l'objet original.
     *
     * @return DemandeIntervention|MepSsi
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * On renvoi l'impact que l'on souhaite mettre en avant.
     *
     * @return Impact|null
     */
    public function getImpactOriginal()
    {
        return $this->impactOriginal;
    }

    /**
     * On ajoute l'impact que l'on souhaite mettre en avant dans l'opération.
     *
     * @param Impact $impact
     */
    public function setImpactOriginal(Impact $impact)
    {
        $this->impactOriginal = $impact;
    }

    /**
     * Permet de renvoyer le nom de la classe de l'objet.
     *
     * @return false|string
     */
    public function getOriginalClass()
    {
        return get_class($this->original);
    }

    /**
     * Permet de récupérer la date utilisée pour le tri.
     *
     * @return \DateTime
     */
    public function getDateTri() : \DateTime
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getMepDebut() ?
                clone $this->getOriginal()->getMepDebut()->setTimeZone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0)
                : clone $this->getOriginal()->getMes()->setTimeZone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
        } elseif ($this->getOriginalClass() === DemandeIntervention::class && $this->getImpactOriginal() instanceof Impact) {
            return clone $this->getImpactOriginal()->getDateDebut();
        } else {
            return clone $this->getOriginal()->getDateDebut();
        }
    }

    /**
     * Renvoi true si l'opération se déroule sur plusieurs jours.
     *
     * @return bool
     */
    public function impactSurPlusieursJours() : bool
    {
        $periodeDebut = $this->getInterventionDebut();
        $periodeFin = $this->getInterventionFin();

        return $periodeDebut->format('d/m/Y') !== $periodeFin->format('d/m/Y');
    }

    /**
     * Renvoi la date et l'heure de début de l'intervention.
     *
     * @return \DateTimeInterface
     */
    public function getInterventionDebut() : \DateTimeInterface
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            if ($this->getOriginal()->getMepDebut()) {
                return clone $this->getOriginal()->getMepDebut();
            } else {
                return clone $this->getOriginal()->getMes();
            }
        } elseif ($this->getOriginalClass() === DemandeIntervention::class && $this->getImpactOriginal() instanceof Impact) {
            return clone $this->getImpactOriginal()->getDateDebut();
        }
        return clone $this->getOriginal()->getDateDebut();
    }

    /**
     * Renvoi la date et l'heure de début de l'intervention timezoné Europe / Paris.
     *
     * @return \DateTimeInterface
     */
    public function getInterventionDebutTz() : \DateTimeInterface
    {
        return $this->getInterventionDebut()->setTimeZone(new \DateTimeZone('Europe/Paris'));
    }

    /**
     * Renvoi la date et l'heure de la fin de l'intervention.
     *
     * @return \DateTimeInterface
     */
    public function getInterventionFin(): \DateTimeInterface
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            if ($this->getOriginal()->getMepFin()) {
                return clone $this->getOriginal()->getMepFin();
            } else {
                if ($this->getOriginal()->getMepDebut()) {
                    return clone $this->getOriginal()->getMepDebut();
                } else {
                    return clone $this->getOriginal()->getMes();
                }
            }
        } elseif ($this->getOriginalClass() === DemandeIntervention::class and $this->getImpactOriginal() instanceof Impact) {
            return clone $this->getImpactOriginal()->getDateFinMax();
        }
        return clone $this->getOriginal()->getDateFinMax();
    }

    /**
     * Renvoi la date et l'heure de fin de l'intervention timezoné Europe / Paris.
     *
     * @return \DateTimeInterface
     */
    public function getInterventionFinTz() : \DateTimeInterface
    {
        return $this->getInterventionFin()->setTimeZone(new \DateTimeZone('Europe/Paris'));
    }

    /**
     * Permet de renvoyer le type de l'opération (gesip ou mepssi).
     *
     * @return string
     */
    public function getOperationType() : string
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return self::TYPE_MEPSSI;
        } else {
            return self::TYPE_GESIP;
        }
    }

    /**
     * Renvoi le statut MepSSI.
     *
     * @return string|null
     */
    public function getMepStatutLabel() : ?string
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getStatut()->getLabel();
        }
        return null;
    }

    /**
     * Renvoi l'id de l'entité.
     *
     * @return int
     */
    public function getId() : int
    {
        return $this->getOriginal()->getId();
    }

    /**
     * Renvoi les composants associés à l'opération.
     *
     * @return Composant[]
     */
    public function getComposants() : array
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getComposants()->toArray();
        } else {
            return [$this->getOriginal()->getComposant()];
        }
    }

    /**
     * Renvoi les labels des composants impactés.
     *
     * @return array
     */
    public function getComposantsLabel() : array
    {
        return array_column($this->getComposants(), 'label');
    }

    /**
     * Renvoi du palier si mepssi.
     *
     * @return string|null
     */
    public function getMepPalier() : ?string
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getPalier();
        }
        return null;
    }

    /**
     * Renvoi les pilotes associés.
     *
     * @return Pilote[]
     */
    public function getPilotes() : array
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getPilotes()->toArray();
        }
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getOriginal()->getComposant()->getPilote()) {
            return [$this->getOriginal()->getComposant()->getPilote()];
        }
        return [];
    }

    /**
     * Renvoi l'équipe associé.
     *
     * @return Service|null
     */
    public function getEquipe() : ?Service
    {
        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getOriginal()->getComposant()->getEquipe()) {
            return $this->getOriginal()->getComposant()->getEquipe();

        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getEquipe();
        }

        return null;
    }

    /**
     * Renvoi le service demandeur associé.
     *
     * @return Service|null
     */
    public function getDemandeur() : ?Service
    {
        return $this->getOriginal()->getDemandePar();
    }

    /**
     * Renvoi les exploitants associés.
     *
     * @return Annuaire[]
     */
    public function getExploitants() : array
    {
        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class) {
            return $this->getOriginal()->getServices()->toArray();
        }

        return [];
    }

    /**
     * Renvoi les composants impactés.
     *
     * @return Composant[]
     */
    public function getComposantsImpactes() : array
    {
        // Si nous sommes sur une demande d'intervention et qu'il y a un impact de mis en avant
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getImpactOriginal()) {
            return $this->getImpactOriginal()->getComposants()->toArray();
        }

        return [];
    }

    /**
     * Renvoi le nombre de jours entre la date de la demande ainsi que la date de la validation.
     *
     * @return int
     */
    public function getDeltaJourValidationGesip() : int
    {
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getOriginal()->getDateAccord()) {
            $dateValidation = (clone $this->getOriginal()->getDateAccord())->setTime(0, 0, 0)->format('U');
            $dateDemande = (clone $this->getOriginal()->getAjouteLe())->setTime(0, 0, 0)->format('U');
            return ceil(($dateValidation - $dateDemande) / 86400);
        }
        return 0;
    }

    /**
     * Permet d'afficher les données pour la colonne "Intervention / Mep".
     *
     * @param bool $html
     *
     * @return string
     */
    public function donneesInterventionMep(bool $html = false) : string
    {
        // On défini les variables utiles
        $out = [];
        $tz = new \DateTimeZone("Europe/Paris");

        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class) {
            $debut = (clone $this->getImpactOriginal()->getDateDebut()->setTimeZone($tz));
            $out[] = "<div><strong>Début :</strong> {$debut->format('d/m/Y H:i')}</div>";
            $out[] = sprintf("<div><strong>Durée min :</strong> %s</div>", ChaineDeCaracteres::minutesEnLectureHumaine($this->getImpactOriginal()->getDureeMinutesMini()));
            $out[] = sprintf("<div><strong>Durée max :</strong> %s</div>", ChaineDeCaracteres::minutesEnLectureHumaine($this->getImpactOriginal()->getDureeMinutesMaxi()));
        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            $debut = $this->getOriginal()->getMepDebut() ? (clone $this->getOriginal()->getMepDebut()->setTimeZone($tz))->format('d/m/Y') : null;
            $fin = $this->getOriginal()->getMepFin() ? (clone $this->getOriginal()->getMepFin()->setTimeZone($tz))->format('d/m/Y') : null;
            $mes = (clone $this->getOriginal()->getMes()->setTimeZone($tz))->format('d/m/Y');

            if ($debut !== null) {
                $tmp = $debut;

                if ($fin !== null && $debut !== $fin) {
                    $tmp .= " au $fin";
                }
                $out[] = $tmp;
            }

            if ($debut != $mes || $fin != $mes) {
                $tmp = null;
                if ($debut !== null) {
                    $tmp = "<br/><strong>Ouverture :</strong> ";
                }
                $out[] = $tmp . $mes;
            }
        }

        // On implode les différentes lignes html, que l'on retourne ensuite.
        $out = implode("\n", $out);
        return (!$html) ? strip_tags($out) : $out;
    }

    /**
     * Permet de trier les données pour la colonne "Intervention / Mep".
     *
     * @return string
     */
    public function donneesInterventionMepTri() : string
    {
        // On défini les variables utiles
        $tz = new \DateTimeZone("Europe/Paris");

        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class) {
            return (clone $this->getImpactOriginal()->getDateDebut()->setTimeZone($tz))->format('Y-m-d H:i');

        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            if ($this->getOriginal()->getMepDebut()) {
                $date = clone $this->getOriginal()->getMepDebut();
            } else {
                $date = clone $this->getOriginal()->getMes();
            }

            return $date->setTimeZone($tz)->format('Y-m-d H:i');
        }

        return "";
    }

    /**
     * Permet d'afficher les données pour la colonne "Composant".
     *
     * @param bool $html
     * @param bool $displayDirectLink
     *
     * @return string
     */
    public function donneesComposant(bool $html = false, bool $displayDirectLink = true) : string
    {
        // On défini les variables utiles
        $out = [];

        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class) {
            $out[] = sprintf(
                '<a href="/restitution/composants/%s" target="_blank">%s</a>',
                $this->getOriginal()->getComposant()->getId(),
                $this->getOriginal()->getComposant()->getLabel()
            )."\n";
            $out[] = sprintf('<div class="text-left">%s</div>', htmlentities($this->getOriginal()->getDescription()))."\n";
            if ($displayDirectLink) {
                $out[] = sprintf('<a href="/demandes/%s" target="_blank">N°%s</a>', $this->getOriginal()->getId(), $this->getOriginal()->getNumero());
            }

        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            foreach ($this->getOriginal()->getComposants() as $composant) {
                $tmp = sprintf('<a href="/restitution/composants/%s" target="_blank">%s</a>', $composant->getId(), $composant->getLabel());
                if ($this->getOriginal()->getComposants()->last() !== $composant) {
                    $tmp .= ", ";
                }
                $out[] = $tmp;
            }
            if ($displayDirectLink) {
                $out[] = "\n";
                $out[] = sprintf('<br/><a href="/calendrier/mep-ssi/%s" target="_blank">N°%s</a>', $this->getOriginal()->getId(), $this->getOriginal()->getId());
            }
        }

        // On implode les différentes lignes html, que l'on retourne ensuite.
        $out = implode("", $out);
        return (!$html) ? html_entity_decode(strip_tags($out)) : $out;
    }

    /**
     * Permet d'afficher les données pour la colonne "Impact / Description".
     *
     * @param bool $html
     *
     * @return string
     */
    public function donneesImpactDescription(bool $html = false) : string
    {
        // On défini les variables utiles
        $out = [];

        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class) {
            $out[] = sprintf('<div><strong>%s</strong></div>', $this->getImpactOriginal()->getNature()->getLabel())."\n";
            $out[] = sprintf('<div class="text-left">%s</div>', htmlentities($this->getImpactOriginal()->getCommentaire()))."\n";

            if ($this->getImpactOriginal()->getComposants()->count() > 0) {
                $out[] = '<div class="operation-impacts">'."\n";
                $out[] = '<div class="operation-impacts-head"><strong>Composants impactés</strong><div class="toggle"><i class="fa fa-eye"></i></div></div>'."\n";
                $out[] = '<ul class="operation-impacts-body">';

                $letter = '';
                foreach ($this->getImpactOriginal()->getComposants() as $composant) {
                    $label = $composant->getLabel();

                    if ($html) {
                        if ($letter != $label[0]) {
                            $letter = $label[0];
                            $out[] = sprintf('<li class="head-letter">%s</li>', $letter);
                        }

                        $out[] = sprintf(
                            '<li><a href="/restitution/composants/%s" target="_blank">%s</a></li>',
                            $composant->getId(),
                            $label
                        );
                    } else {
                        $out[] = $label;
                        if ($this->getImpactOriginal()->getComposants()->last() !== $composant) {
                            $out[] = ", ";
                        }
                    }
                }

                $out[] = '</ul>';
                $out[] = '</div';
            }

        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            $out[] = htmlentities($this->getOriginal()->getDescription());
        }

        // On implode les différentes lignes html, que l'on retourne ensuite.
        $out = implode("", $out);
        return (!$html) ? html_entity_decode(strip_tags($out)) : $out;
    }

    /**
     * Permet d'afficher les données pour la colonne "Palier".
     *
     * @return string
     */
    public function donneesPalier() : string
    {
        if ($this->getOriginalClass() === MepSsi::class) {
            return $this->getOriginal()->getPalier();
        }
        return '';
    }

    /**
     * Permet d'afficher les données pour la colonne "Équipe".
     *
     * @param bool $html
     *
     * @return string
     */
    public function donneesEquipe(bool $html = false) : string
    {
        // On défini nos variables de départ
        $out = '';
        $equipe = $this->getEquipe();

        // Si il existe une équipe
        if ($equipe !== null) {
            $out = sprintf('<a href="/restitution/equipes/%s" target="_blank">%s</a>', $equipe->getId(), $equipe->getLabel());
        }

        // On retourne ensuite la sortie
        return (!$html) ? strip_tags($out) : $out;
    }

    /**
     * Permet d'afficher les données pour la colonne "Pilote".
     *
     * @param bool $html
     *
     * @return string
     */
    public function donneesPilote(bool $html = false) : string
    {
        // On défini les variables utiles
        $out = [];

        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getOriginal()->getComposant()->getPilote()) {
            $pilote = $this->getOriginal()->getComposant()->getPilote();
            if ($pilote) {
                $out[] = sprintf('<a href="/restitution/pilotes/%s" target="_blank">%s</a>', $pilote->getId(), $pilote->getNomCompletCourt());
            }

        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            foreach ($this->getOriginal()->getPilotes() as $pilote) {
                $tmp = sprintf('<a href="/restitution/pilotes/%s" target="_blank">%s</a>', $pilote->getId(), $pilote->getNomCompletCourt());
                if ($this->getOriginal()->getPilotes()->last() !== $pilote) {
                    $tmp .= ", ";
                }
                $out[] = $tmp;
            }
        }

        // On implode les différentes lignes html, que l'on retourne ensuite.
        $out = implode("", $out);
        return (!$html) ? strip_tags($out) : $out;
    }

    /**
     * Permet d'afficher les données pour trier la colonne "Pilote".
     *
     * @param bool $html
     *
     * @return string
     */
    public function donneesPiloteTri() : string
    {
        // On défini les variables utiles
        $out = [];

        // Si nous sommes sur une demande d'intervention
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getOriginal()->getComposant()->getPilote()) {
            $out[] = $this->getOriginal()->getComposant()->getPilote()->getNomPrenomCompletLong();

        // Si nous sommes sur une MEP SSI
        } elseif ($this->getOriginalClass() === MepSsi::class) {
            foreach ($this->getOriginal()->getPilotes() as $pilote) {
                $out[] = $pilote->getNomPrenomCompletLong();
            }
        }

        // On implode les différentes valeurs.
        return implode(" ", $out);
    }

    /**
     * Permet d'afficher les données pour la colonne "ESI".
     *
     * @param bool $html
     *
     * @return string
     */
    public function donneesEsi(bool $html = false) : string
    {
        // On défini les variables utiles
        $out = [];

        // Si il y a des exploitants à afficher
        foreach ($this->getExploitants() as $service) {
            $tmp = sprintf('<a href="/restitution/esi/%s" target="_blank">%s</a>', $service->getService()->getId(), $service->getService()->getLabel());
            if ($this->getOriginal()->getServices()->last() !== $service) {
                $tmp .= ", ";
            }
            $out[] = $tmp;
        }

        // On implode les différentes lignes html, que l'on retourne ensuite.
        $out = implode("", $out);
        return (!$html) ? strip_tags($out) : $out;
    }

    /**
     * Permet d'afficher les données pour la colonne "Date demande".
     *
     * @return string
     */
    public function donneesDateDemande() : string
    {
        if ($this->getOriginalClass() === DemandeIntervention::class) {
            return (clone $this->getOriginal()->getAjouteLe())->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y');
        }
        return '';
    }

    /**
     * Permet d'afficher les données pour la colonne "Date validation".
     *
     * @return string
     */
    public function donneesDateValidation() : string
    {
        if ($this->getOriginalClass() === DemandeIntervention::class && $this->getOriginal()->getDateAccord()) {
            return (clone $this->getOriginal()->getDateAccord())->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y');
        }
        return '';
    }
}
