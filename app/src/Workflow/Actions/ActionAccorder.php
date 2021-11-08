<?php

namespace App\Workflow\Actions;

use App\Entity\Service;
use App\Form\Demande\Workflow\AccorderType;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionAccorder extends Action
{
    /** @var string NOM */
    public const NOM = "Accorder";

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        $form = $this->getMachineEtat()->getFormBuilder(AccorderType::class);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/accorder.html.twig', [
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
        $form = $me->getFormBuilder(AccorderType::class);
        $form->submit($request->request->all());
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $commentaire = empty($formData['commentaire']) ? null : $formData['commentaire'];
            // Création de la liste des destinataires
            if ($formData['envoyerMail']) {
                $this->envoyerMail($commentaire);
            }
            // changement d'état et enregistrement des données collectées
            $me->changerEtat(
                EtatAccordee::class,
                [
                    'commentaire' => $commentaire
                ]
            );
            // On ajoute un message flash
            $this->addFlash("success", "La demande a été accordée avec succès.");
            // retourne une réponse succès
            return $this->retourSuccess();
        } else {
            return $this->retourErreur($form);
        }
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
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Accorder";
    }

    /**
     * Retourne les classes du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonClasses(): string
    {
        return "btn-success";
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param string $commentaire
     */
    private function envoyerMail(string $commentaire = null)
    {
        // Construction de la liste des destinataires
        $machineEtat = $this->getMachineEtat();
        $demandeIntervention = $machineEtat->getDemandeIntervention();
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_DEMANDEUR,
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_INTERVENANTS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
                DestinatairesCollection::OPTION_SUPERVISION,
            ]
        );
        // Ajoute les services consultés si il y a eu consultation, sinon ajoute les annuaires des services des composants impactés
        $demandeHistoriqueStatus = $demandeIntervention->getHistoriqueStatus();
        if (in_array($demandeHistoriqueStatus[1]->getStatus(), [EtatConsultationEnCours::class, EtatConsultationEnCoursCdb::class])) {
            $destinatairesCollection->ajouteServicesConsultes();
        } else {
            $destinatairesCollection->ajouteAnnuairesComposantsImpactes();
        }
        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:ACCORD] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/accorder.text.twig')
            ->htmlTemplate('emails/demandes/workflow/accorder.html.twig')
            ->context([
                'demandeIntervention'   => $demandeIntervention,
                'commentaire'           => $commentaire,
            ]);
        $machineEtat->getMailer()->send($emailMessage);
    }
}
