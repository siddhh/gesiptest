<?php

namespace App\Workflow\Actions;

use App\Entity\Service;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;
use App\Form\Demande\Workflow\AnnulerType;

class ActionAnnuler extends Action
{
   /** @var string NOM */
    public const NOM = "Annuler";

   /**
     * Renvoie True, si l'action est exécutable.
     * (Si l'utilisateur courant est ROLE_GESTION)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        if (parent::peutEtreExecutee()) {
            return $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
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
        $form = $this->getMachineEtat()->getFormBuilder(AnnulerType::class);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/annuler.html.twig', [
            'shortClassName' => $this->getShortClassName(),
            'form' => $form->createView()
        ]);
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
        // On récupère le formulaire AnnulerType
        $form = $this->getMachineEtat()->getFormBuilder(AnnulerType::class);
        // On lui passe les données saisies par l'utilisateur
        $form->submit($request->request->all());

        // On traite notre formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            // On récupère les données et dont le commentaire
            $formData = $form->getData();
            $commentaire = $formData['commentaire'];

            // On vient essayer de mettre à jour le statut de l'état
            $this->getMachineEtat()->changerEtat(EtatAnnulee::class, [
                'commentaire' => $commentaire
            ]);

            // Si "envoyerMail" est vrai, alors on envoi le mail
            if ($formData['envoyerMail']) {
                $this->envoyerMail($commentaire);
            }

            // On ajoute un message flash
            $this->addFlash("success", "La demande a été annulée avec succès.");

            // On renvoi un succès
            return $this->retourSuccess();
        } else {
            // On renvoi les erreurs
            return $this->retourErreur($form);
        }
    }

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Annuler la demande";
    }

    /**
     * Retourne les classes du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonClasses(): string
    {
        return "btn-danger";
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param string $commentaire
     */
    private function envoyerMail(string $commentaire): void
    {
        // Construction de la liste des destinataires
        $machineEtat = $this->getMachineEtat();
        $demandeIntervention = $machineEtat->getDemandeIntervention();

        // En fonction du contexte / de la phase atteinte les destinataires sont différents
        $contexte = null;
        $destinatairesCollectionsArray = [
            DestinatairesCollection::OPTION_DEMANDEUR,
            DestinatairesCollection::OPTION_ADMINS,
            DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
        ];
        $demandeHistoriqueStatus = $demandeIntervention->getHistoriqueStatus();
        switch ($demandeHistoriqueStatus[0]->getStatus()) {
            case EtatInstruite::class:
                $contexte = 'INSTRUCTION';
                $destinatairesCollectionsArray = array_merge(
                    $destinatairesCollectionsArray,
                    [
                        DestinatairesCollection::OPTION_INTERVENANTS,
                        DestinatairesCollection::OPTION_SERVICES_COMPOSANT,
                        DestinatairesCollection::OPTION_SERVICES_CONSULTES,
                        DestinatairesCollection::OPTION_SERVICES_IMPACTES,
                    ]
                );
                break;
            case EtatAccordee::class:
            case EtatInterventionEnCours::class:
            case EtatSaisirRealise::class:
                $contexte = 'ACCORD';
                $destinatairesCollectionsArray = array_merge(
                    $destinatairesCollectionsArray,
                    [
                        DestinatairesCollection::OPTION_INTERVENANTS,
                        DestinatairesCollection::OPTION_SERVICES_COMPOSANT,
                        DestinatairesCollection::OPTION_SERVICES_CONSULTES,
                        DestinatairesCollection::OPTION_SUPERVISION,
                    ]
                );
                break;
            default:
                $contexte = 'ANALYSE';
        }
        $destinatairesCollection = new DestinatairesCollection($demandeIntervention, $destinatairesCollectionsArray);
        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:ANNULATION APRES {$contexte}] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/annuler.text.twig')
            ->htmlTemplate('emails/demandes/workflow/annuler.html.twig')
            ->context([
               'demandeIntervention'    => $demandeIntervention,
               'commentaire'            => $commentaire,
               'contexte'               => $contexte,
            ]);
        $machineEtat->getMailer()->send($emailMessage);
    }
}
