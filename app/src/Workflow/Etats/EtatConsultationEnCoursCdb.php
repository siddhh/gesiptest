<?php

namespace App\Workflow\Etats;

use App\Workflow\Etat;
use App\Entity\Service;
use App\Workflow\Actions\ActionDonnerAvisCdb;

class EtatConsultationEnCoursCdb extends Etat
{

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Consultation en cours du CDB";
    }

    /**
     * Nous pouvons entrer dans cet état uniquement si l'utilisateur courant possède le rôle gestion.
     * @return bool
     */
    public function peutEntrer(): bool
    {
        return $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionDonnerAvisCdb::class
        ];
    }
}
