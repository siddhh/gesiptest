<?php

namespace App\Workflow\Actions;

use App\Entity\Service;
use App\Workflow\Action;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ActionInstruire extends Action
{
    /** @var string NOM */
    public const NOM = "Instruire";

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        if (parent::peutEtreExecutee()) {
            // On ne peut pas instruire une demande si on n'a pas un role gestion
            if (!$this->getMachineEtat()->serviceEst(Service::ROLE_GESTION)) {
                return false;
            }
            // On ne peut pas instruire une demande directement si le CDB doit être consulté avant
            $statusDonnees = $this->getMachineEtat()->getDemandeIntervention()->getStatusDonnees();
            if (!empty($statusDonnees['avecCdb']) && $statusDonnees['avecCdb']) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Renvoie True, si l'utilisateur connecté est habilité a lancer cette action.
     * (Si l'utilisateur courant est ADMIN, ou si celui-ci est DME et fait parti de l'équipe du composant de la demande)
     * @return bool
     */
    public function estHabilite(): bool
    {
        $me = $this->getMachineEtat();
        return (
            $me->serviceEst(Service::ROLE_ADMIN) ||
            $me->serviceEst(Service::ROLE_DME) &&
            $me->getDemandeIntervention()->getComposant()->getEquipe() === $me->getServiceConnecte()
        );
    }

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        return null;
    }

    /**
     * Traitement de l'action.
     * @param Request|null $request
     * @return JsonResponse
     * @throws EntreeImpossibleException
     * @throws SortieImpossibleException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function traitement(?Request $request = null): JsonResponse
    {
        // Changement d'état
        $this->getMachineEtat()->changerEtat(EtatInstruite::class);

        // On ajoute un message flash
        $this->addFlash("success", "La demande a été instruite.");

        return $this->retourSuccess();
    }

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Instruire la demande";
    }

    /**
     * Retourne les classes du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonClasses(): string
    {
        return "btn-success";
    }
}
