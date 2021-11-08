<?php

namespace App\Workflow\Etats;

use App\Workflow\Etat;
use App\Entity\Service;

class EtatAnnulee extends Etat
{
    /**
     * Retourne True si nous pouvons entrer dans cet état.
     * @return bool
     */
    public function peutEntrer(): bool
    {
        // On peut entrer dans cet état uniquement si on dispose du role gestion
        //    ou si on est en mode commande (typiquement une tache cron)
        return 'cli' === php_sapi_name()
            || $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
    }

    /**
     * Récupération du libelle de l'état.
     * @return string
     */
    public function getLibelle(): string
    {
        return "Annulée";
    }
}
