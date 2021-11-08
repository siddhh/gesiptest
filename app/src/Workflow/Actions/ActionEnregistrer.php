<?php

namespace App\Workflow\Actions;

use App\Workflow\Action;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ActionEnregistrer extends Action
{
    /** @var string NOM */
    public const NOM = "Enregistrer";

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
        // On change d'état
        $this->getMachineEtat()->changerEtat(EtatBrouillon::class);

        // On ajoute un message flash
        $this->addFlash("success", "Votre demande N°{$this->getMachineEtat()->getDemandeIntervention()->getNumero()} a bien été enregistrée dans vos brouillons.");

        return $this->retourSuccess();
    }
}
