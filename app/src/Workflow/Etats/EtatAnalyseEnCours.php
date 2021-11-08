<?php

namespace App\Workflow\Etats;

use App\Entity\Service;
use App\Workflow\Actions\ActionRenvoyer;
use App\Workflow\Actions\ActionAnnuler;
use App\Workflow\Actions\ActionRefuser;
use App\Workflow\Actions\ActionLancerInformation;
use App\Workflow\Actions\ActionLancerConsultation;
use App\Workflow\Etat;

class EtatAnalyseEnCours extends Etat
{
    /**
     * Nous pouvons sortir de cet état uniquement si l'utilisateur courant possède le rôle gestion.
     * @return bool
     */
    public function peutSortir(): bool
    {
        return $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Analyse en cours";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionAnnuler::class,
            ActionRefuser::class,
            ActionRenvoyer::class,
            ActionLancerInformation::class,
            ActionLancerConsultation::class,
        ];
    }
}
