<?php

namespace App\Workflow\Etats;

use App\Workflow\Actions\ActionEnregistrer;
use App\Workflow\Actions\ActionEnvoyer;
use App\Workflow\Etat;

class EtatBrouillon extends Etat
{
    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Brouillon";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionEnregistrer::class,
            ActionEnvoyer::class,
        ];
    }
}
