<?php

namespace App\Workflow\Exceptions;

use App\Workflow\Etat;

class EntreeImpossibleException extends WorkflowException
{

    /**
     * Constructeur de EntreeImpossibleException.
     * @param Etat $etat
     */
    public function __construct(Etat $etat)
    {
        parent::__construct(
            sprintf("Impossible d'entrer dans l'état \"%s\".", $etat->getLibelle())
        );
    }
}
