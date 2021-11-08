<?php

namespace App\Workflow\Etats;

use App\Workflow\Actions\ActionAnnuler;
use App\Workflow\Actions\ActionSaisirRealise;
use App\Workflow\Etat;

class EtatSaisirRealise extends Etat
{
    /**
     * Autorise uniquement à passer dans cet état si la date de fin d'intervention est passée.
     * @return bool
     */
    public function peutEntrer(): bool
    {
        return true;
    }

    /**
     * Autorise uniquement à sortir de cet état si le service connecté est un service intervenant.
     * @return bool
     */
    public function peutSortir(): bool
    {
        return true;
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Réalisé à saisir";
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
