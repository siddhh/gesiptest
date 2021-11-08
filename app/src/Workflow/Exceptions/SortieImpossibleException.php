<?php

namespace App\Workflow\Exceptions;

use App\Workflow\Etat;

class SortieImpossibleException extends WorkflowException
{

    /**
     * Constructeur de SortieImpossibleException.
     * @param Etat $etat
     */
    public function __construct(Etat $etat)
    {
        parent::__construct(
            sprintf("Impossible de sortir de l'état \"%s\".", $etat->getLibelle())
        );
    }
}
