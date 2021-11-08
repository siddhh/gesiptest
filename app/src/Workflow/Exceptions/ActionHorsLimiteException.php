<?php

namespace App\Workflow\Exceptions;

use App\Workflow\Action;
use App\Workflow\MachineEtat;

class ActionHorsLimiteException extends WorkflowException
{

    /**
     * Constructeur de ActionInterditeException.
     * @param Action $action
     * @param MachineEtat $mae
     * @param int $delai
     */
    public function __construct(Action $action, MachineEtat $mae, int $delai)
    {
        parent::__construct(
            sprintf(
                "L'action \"%s\" est interdite dans l'état \"%s\" de la demande d'intervention au-delà de %d jours.",
                $action::NOM,
                $mae->getEtatActuel()->getLibelle(),
                $delai
            )
        );
    }
}
