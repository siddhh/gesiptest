<?php

namespace App\Workflow\Actions;

use App\Entity\References\MotifRenvoi;
use App\Entity\Service;
use App\Form\Demande\Workflow\RenvoyerType;
use App\Workflow\Action;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatRenvoyee;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionRenvoyer extends Action
{
    /** @var string NOM */
    public const NOM = "Renvoyer";

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        // Seuls les ceux qui ont un rôle de gestion peuvent renvoyer une demande
        if (!$this->getMachineEtat()->serviceEst(Service::ROLE_GESTION)) {
            return false;
        }

        // Interdit la possibilité de renvoyer une nouvelle fois la demande si elle a déjà fait l'objet de 2 premiers renvois
        $renvoisCount = 0;
        $statutsPrecedents = $this->getMachineEtat()->getDemandeIntervention()->getHistoriqueStatus()->getValues();
        foreach ($statutsPrecedents as $statutsPrecedent) {
            if ($statutsPrecedent->getStatus() == EtatRenvoyee::class) {
                if ($renvoisCount > 0) {
                    return false;
                }
                $renvoisCount++;
            }
        }

        return true;
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
        $form = $this->getMachineEtat()->getFormBuilder(RenvoyerType::class);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/renvoyer.html.twig', [
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
        $form = $this->getMachineEtat()->getFormBuilder(RenvoyerType::class);
        $form->submit($request->request->all());

        if ($form->isSubmitted() && $form->isValid()) {
            // On met en forme les données pour sauvegarder en base de données
            $formData = $form->getData();
            $donneesMotifs = [];

            foreach ($formData['motifs'] as $motif) {
                /** @var MotifRenvoi $motifRenvoi */
                $motifRenvoi = $motif['motif'];
                $motifCommentaire = $motif['commentaire'];

                $donneesMotifs[] = [
                    'motif' => [
                        'id' => $motifRenvoi->getId(),
                        'label' => $motifRenvoi->getLabel()
                    ],
                    'commentaire' => $motifCommentaire
                ];
            }

            // Si l'utilisateur souhaite envoyer un mail
            if ($formData['envoyerMail']) {
                $this->envoyerMail($donneesMotifs);
            }

            // On change l'état
            $this->getMachineEtat()->changerEtat(EtatRenvoyee::class, $donneesMotifs);

            // On ajoute un message flash
            $this->addFlash("success", "La demande a été renvoyée avec succès.");

            // On envoie notre réponse
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
        return "Renvoyer la demande";
    }

    /**
     * Retourne les classes du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonClasses(): string
    {
        return "btn-warning";
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param array $donneesMotifs
     */
    private function envoyerMail(array $donneesMotifs)
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
        switch ($demandeIntervention->getStatus()) {
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
                $contexte = 'ACCORD';
                $destinatairesCollectionsArray = array_merge(
                    $destinatairesCollectionsArray,
                    [
                        DestinatairesCollection::OPTION_INTERVENANTS,
                        DestinatairesCollection::OPTION_SERVICES_COMPOSANT,
                        DestinatairesCollection::OPTION_SERVICES_CONSULTES_OU_IMPACTES,
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
            ->subject("{$composantLabel} [GESIP:RENVOI APRES {$contexte}] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/renvoyer.text.twig')
            ->htmlTemplate('emails/demandes/workflow/renvoyer.html.twig')
            ->context([
                'demandeIntervention'   => $demandeIntervention,
                'serviceConnecte'       => $machineEtat->getServiceConnecte(),
                'motifs'                => $donneesMotifs,
                'contexte'              => $contexte,
            ]);
        $this->getMachineEtat()->getMailer()->send($emailMessage);
    }
}
