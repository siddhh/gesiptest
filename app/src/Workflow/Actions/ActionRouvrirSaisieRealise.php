<?php

namespace App\Workflow\Actions;

use App\Workflow\Action;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatTerminee;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use App\Workflow\Exceptions\ActionHorsLimiteException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Service;

class ActionRouvrirSaisieRealise extends Action
{
    /** @var string NOM */
    public const NOM = "Rouvrir la saisie du réalisé";

    // délai (en jours) par rapport au passage à l'état "Terminée" ou "Réussie" ou "Échouée" pendant lequel l'action est autorisée
    /** @var int DELAI */
    public const DELAI = 30;

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        if (parent::peutEtreExecutee()) {
            if (!$this->getMachineEtat()->serviceEst(Service::ROLE_GESTION)) {
                return false;
            }

            $dtNow = new \DateTime();
            $dtMaj = null;
            $statutsPrecedents = $this->getMachineEtat()->getDemandeIntervention()->getHistoriqueStatus()->getValues();
            foreach ($statutsPrecedents as $statutPrecedent) {
                if (in_array($statutPrecedent->getStatus(), [EtatTerminee::class, EtatInterventionReussie::class, EtatInterventionEchouee::class])) {
                    $dtMaj = $statutPrecedent->getMajLe();
                    $dtMaj->add(new \DateInterval(sprintf("P%dD", self::DELAI)));
                    break;
                }
            }
            if (($dtMaj == null) || ($dtMaj < $dtNow)) {
                throw new ActionHorsLimiteException($this, $this->getMachineEtat(), self::DELAI);
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
     *
     */
    public function traitement(?Request $request = null): JsonResponse
    {
        // Supprime l'historique jusqu'à l'accord (on peut saisir le réalisé dès qu'une intervention est accordée donc dans tous les états suivants)
        $me = $this->getMachineEtat();
        $em = $me->getEntityManager();
        $demandeIntervention = $me->getDemandeIntervention();
        foreach ($demandeIntervention->getHistoriqueStatus() as $historiqueStatus) {
            $status = $historiqueStatus->getStatus();
            if (EtatAccordee::class === $status) {
                break;
            } elseif (in_array($status, [EtatSaisirRealise::class, EtatInterventionEchouee::class, EtatInterventionReussie::class, EtatTerminee::class])) {
                $em->remove($historiqueStatus);
            }
        }

        // Puis on place la demande dans l'état EtatSaisirRealise pour permettre aux services de saisir le réalisé comme dans le processus "normal"
        $me->changerEtat(EtatSaisirRealise::class, []);

        // On ajoute un message flash
        $this->addFlash("success", "Votre réouverture de la saisie du réalisé a été prise en compte.");

        // retourne une réponse succès
        return $this->retourSuccess();
    }

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Rouvrir la saisie du réalisé";
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
