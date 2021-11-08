<?php

namespace App\Workflow\Etats;

use App\Workflow\Etat;
use App\Entity\Service;

class EtatRefusee extends Etat
{
    /**
     * Retourne True si nous pouvons entrer dans cet état.
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
        return "Refusée";
    }
}
