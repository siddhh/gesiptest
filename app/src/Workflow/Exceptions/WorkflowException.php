<?php

namespace App\Workflow\Exceptions;

class WorkflowException extends \Exception
{

    /**
     * Constructeur du workflow
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message, 0, null);
    }
}
