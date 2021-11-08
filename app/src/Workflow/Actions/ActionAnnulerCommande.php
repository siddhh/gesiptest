<?php

namespace App\Workflow\Actions;

use App\Workflow\Actions\ActionAnnuler;

class ActionAnnulerCommande extends ActionAnnuler
{

    /** @var string NOM */
    public const NOM = "Annuler (Commande / Cron)";

    /**
     * Renvoie True, si l'action est exécutable.
     *  (on autorise cette action uniquement si on est dans le cadre d'une commande => mode cli)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        return 'cli' === php_sapi_name();
    }

    /**
     * Renvoie True, si l'utilisateur connecté est habilité a lancer cette action.
     * (on est habilité si on est dans le cadre d'une commande => mode cli)
     * @return bool
     */
    public function estHabilite(): bool
    {
        return 'cli' === php_sapi_name();
    }
}
