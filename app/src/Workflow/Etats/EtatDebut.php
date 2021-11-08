<?php

namespace App\Workflow\Etats;

use App\Workflow\Actions\ActionEnregistrer;
use App\Workflow\Actions\ActionEnvoyer;
use App\Workflow\Etat;

class EtatDebut extends Etat
{
    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionEnvoyer::class,
            ActionEnregistrer::class
        ];
    }
}
