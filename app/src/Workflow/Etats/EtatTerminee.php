<?php

namespace App\Workflow\Etats;

use App\Workflow\Etat;
use App\Workflow\Actions\ActionSaisirRealise;
use App\Workflow\Actions\ActionRouvrirSaisieRealise;

class EtatTerminee extends Etat
{

    /**
     * Autorise uniquement à passer dans cet état si la date de fin d'intervention minimum est passée.
     * @return bool
     */
    public function peutEntrer(): bool
    {
        $dtNow = new \DateTime();
        $demandeIntervention = $this->getMachineEtat()->getDemandeIntervention();
        $data = $demandeIntervention->getStatusDonnees();
        return $demandeIntervention->getDateFinMini() <= $dtNow
            && !empty($data[ActionSaisirRealise::DONNEES_SAISIES]);
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Terminée";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionRouvrirSaisieRealise::class
        ];
    }
}
