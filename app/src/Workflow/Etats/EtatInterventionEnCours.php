<?php

namespace App\Workflow\Etats;

use App\Workflow\Etat;
use App\Workflow\Actions\ActionSaisirRealise;
use App\Workflow\Actions\ActionAnnuler;

class EtatInterventionEnCours extends Etat
{

    /**
     * Autorise uniquement à passer dans cet état si la date de début d'intervention est passée.
     * @return bool
     */
    public function peutEntrer(): bool
    {
        $dtNow = new \DateTime();
        $demandeIntervention = $this->getMachineEtat()->getDemandeIntervention();
        return $demandeIntervention->getDateDebut() <= $dtNow;
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Intervention en cours";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionSaisirRealise::class,
            ActionAnnuler::class,
        ];
    }
}
