<?php

namespace App\Workflow\Etats;

use App\Workflow\Etat;
use App\Workflow\Actions\ActionRouvrirSaisieRealise;

class EtatInterventionEchouee extends Etat
{

    /**
     * Autorise uniquement à passer dans cet état si la date de fin d'intervention minimum est passée.
     * @return bool
     */
    public function peutEntrer(): bool
    {
        $saisieRealises = $this->getMachineEtat()->getDemandeIntervention()->getSaisieRealises();
        return !empty($saisieRealises);
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Échouée";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        if ($this->getMachineEtat()->getDemandeIntervention()->getPremiereDateSaisieRealise()->diff(new \DateTime('now'))->days >= ActionRouvrirSaisieRealise::DELAI) {
            return [];
        } else {
            return [ActionRouvrirSaisieRealise::class];
        }
    }
}
