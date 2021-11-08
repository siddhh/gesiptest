<?php

namespace App\Workflow\Etats;

use App\Entity\Service;
use App\Workflow\Actions\ActionRenvoyer;
use App\Workflow\Actions\ActionAnnuler;
use App\Workflow\Actions\ActionRefuser;
use App\Workflow\Actions\ActionAccorder;
use App\Workflow\Etat;

class EtatInstruite extends Etat
{

    /**
     * Autorise uniquement les services ayant le rôle ROLE_GESTION à passer une demande en état "instruite".
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
        return "Instruite";
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
            ActionRefuser::class,
            ActionAccorder::class,
        ];
    }
}
