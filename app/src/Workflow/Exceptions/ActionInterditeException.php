<?php

namespace App\Workflow\Exceptions;

use App\Workflow\Action;
use App\Workflow\MachineEtat;

class ActionInterditeException extends WorkflowException
{

    /**
     * Constructeur de ActionInterditeException.
     * @param Action $action
     * @param MachineEtat $mae
     */
    public function __construct(Action $action, MachineEtat $mae)
    {
        parent::__construct(
            sprintf(
                "L'action \"%s\" est interdite dans l'état \"%s\" de la demande d'intervention.",
                $action::NOM,
                $mae->getEtatActuel()->getLibelle()
            )
        );
    }
}
