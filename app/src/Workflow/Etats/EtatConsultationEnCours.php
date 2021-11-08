<?php

namespace App\Workflow\Etats;

use App\Entity\Service;
use App\Workflow\Actions\ActionDonnerAvis;
use App\Workflow\Actions\ActionInstruire;
use App\Workflow\Actions\ActionLancerConsultationCdb;
use App\Workflow\Etat;

class EtatConsultationEnCours extends Etat
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
        return "Consultation en cours";
    }

    /**
     * Récupération de la liste des actions possibles. (Défaut: array vide)
     * @return array
     */
    public function getActions(): array
    {
        return [
            ActionDonnerAvis::class,
            ActionInstruire::class,
            ActionLancerConsultationCdb::class
        ];
    }
}
