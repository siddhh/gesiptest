<?php

namespace App\Workflow\Etats;

use App\Entity\Service;
use App\Workflow\Actions\ActionRenvoyer;
use App\Workflow\Actions\ActionAnnuler;
use App\Workflow\Actions\ActionSaisirRealise;
use App\Workflow\Etat;

class EtatAccordee extends Etat
{
    /**
     * Retourne True si nous pouvons entrer dans cet état. (Défaut: true)
     * Ici, si le service connecté est bien ROLE_GESTION
     * @return bool
     */
    public function peutEntrer(): bool
    {
        return $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Accordée";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionRenvoyer::class,
            ActionAnnuler::class,
            ActionSaisirRealise::class,
        ];
    }
}
