<?php

namespace App\Workflow\Actions;

use App\Entity\Composant\Annuaire;
use App\Entity\Service;
use App\Form\Demande\Workflow\LancerInformationType;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatInstruite;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionLancerInformation extends Action
{
    /** @var string NOM */
    public const NOM = "Lancer l'information";

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        $form = $this->getMachineEtat()->getFormBuilder(LancerInformationType::class, [], [ 'mae' => $this->getMachineEtat() ]);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/lancer-information.html.twig', [
            'shortClassName' => $this->getShortClassName(),
            'form' => $form->createView()
        ]);
    }

    /**
     * Traitement de l'action.
     * @param Request|null $request
     * @return JsonResponse
     */
    public function traitement(?Request $request = null): JsonResponse
    {
        $me = $this->getMachineEtat();
        $form = $me->getFormBuilder(LancerInformationType::class, [], [ 'mae' => $this->getMachineEtat() ]);
        $form->submit($request->request->all());
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $annuaires = $formData['annuaires']['ids']->toArray();
            // envoi mail si nécessaire
            if ($formData['envoyerMail']) {
                $this->envoyerMail($annuaires);
            }
            // On met en forme les informations à sauvegarder lors du changement d'état
            $saveAnnuaires = [];
            foreach ($annuaires as $annuaire) {
                $saveAnnuaires[] = $annuaire->getId();
            }
            // changement d'état
            $me->changerEtat(EtatInstruite::class, ['annuaires' => $saveAnnuaires]);

            // On ajoute un message flash
            $this->addFlash("success", "L'information auprès des services a été lancée avec succès. La demande est donc désormais instruite.");

            // retourne une réponse succès
            return $this->retourSuccess();
        } else {
            return $this->retourErreur($form);
        }
    }

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Lancer l'information";
    }

    /**
     * Retourne les classes du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonClasses(): string
    {
        return "btn-primary";
    }

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
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param Annuaire[] $annuaires
     */
    private function envoyerMail(array $annuaires)
    {
        // Construction du mail et envoi
        $demandeIntervention = $this->getMachineEtat()->getDemandeIntervention();
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_DEMANDEUR,
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
                DestinatairesCollection::OPTION_INTERVENANTS,
            ]
        );
        $destinatairesCollection->ajouteDestinataires($annuaires);
        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:Pour information] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/lancer-information.text.twig')
            ->htmlTemplate('emails/demandes/workflow/lancer-information.html.twig')
            ->context([
                    'demandeIntervention' => $this->getMachineEtat()->getDemandeIntervention(),
                ]);
        $this->getMachineEtat()->getMailer()->send($emailMessage);
    }
}
