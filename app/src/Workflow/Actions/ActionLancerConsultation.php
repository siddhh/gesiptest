<?php

namespace App\Workflow\Actions;

use App\Entity\Composant\Annuaire;
use App\Entity\Service;
use App\Form\Demande\Workflow\LancerConsultationType;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionLancerConsultation extends Action
{
    /** @var string NOM */
    public const NOM = "Lancer la consultation";

    const ANNUAIRES = 'annuaires';

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        $form = $this->getMachineEtat()->getFormBuilder(LancerConsultationType::class, [], [ 'mae' => $this->getMachineEtat() ]);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/lancer-consultation.html.twig', [
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
        // On charge le formulaire
        $form = $this->getMachineEtat()->getFormBuilder(LancerConsultationType::class, [], [ 'mae' => $this->getMachineEtat() ]);
        // On soumet le formulaire avec les informations passées dans la requête par l'utilisateur
        $form->submit($request->request->all());

        // Si le formulaire est soumis et valide
        if ($form->isSubmitted() && $form->isValid()) {
            // On récupère les données saisies et validées
            $formData = $form->getData();
            $dateLimite = $formData['dateLimite'];
            $annuairesConsultes = $formData['annuaires']['ids'];

            // Si l'utilisateur souhaite envoyer un mail
            if ($formData['envoyerMail']) {
                // Flag permettant d'indiquer si nous sommes sur une consultation après renvoi
                $historiqueEtats = $this->getMachineEtat()->getDemandeIntervention()->getHistoriqueStatus();
                $siConsultationApresRenvoi = false;
                $demandeDejaRenvoyee = false;
                foreach ($historiqueEtats as $historique) {
                    if ($historique->getStatus() === EtatRenvoyee::class) {
                        $demandeDejaRenvoyee = true;
                    } elseif ($demandeDejaRenvoyee && $historique->getStatus() === EtatConsultationEnCours::class) {
                        $siConsultationApresRenvoi = true;
                        break;
                    }
                }
                $this->envoyerMail($siConsultationApresRenvoi, $dateLimite, $annuairesConsultes->toArray());
            }

            // On met en forme les données à enregistrer en base de données
            $saveData = [
                'dateLimite' => $dateLimite->format('d/m/Y'),
                'avecCdb' => $formData['avecCdb'],
                self::ANNUAIRES => []
            ];
            /** @var Annuaire $annuaire */
            foreach ($annuairesConsultes as $annuaire) {
                $saveData[self::ANNUAIRES][] = $annuaire->getId();
            }

            // On change d'état
            $this->getMachineEtat()->changerEtat(EtatConsultationEnCours::class, $saveData);

            // On ajoute un message flash
            $this->addFlash("success", "La consultation auprès des services a été lancée avec succès.");

            // Tout s'est bien passé, donc on envoie une réponse en succès
            return $this->retourSuccess();
        // Sinon, on envoi une erreur
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
        return "Lancer la consultation";
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
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param bool $apresRenvoi
     * @param \DateTimeInterface $dateLimite
     * @param array $annuairesConsultes
     */
    private function envoyerMail(bool $apresRenvoi, \DateTimeInterface $dateLimite, array $annuairesConsultes)
    {
        // Construction du mail et envoi
        $demandeIntervention = $this->getMachineEtat()->getDemandeIntervention();
        $composantEquipe = $demandeIntervention->getComposant()->getEquipe();
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_INTERVENANTS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
            ]
        );
        $destinatairesCollection->ajouteDestinataires($annuairesConsultes);
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:Pour accord" . (($apresRenvoi)?" suite à renvoi de la demande":"") . "] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/consulter.text.twig')
            ->htmlTemplate('emails/demandes/workflow/consulter.html.twig')
            ->context([
                'demandeIntervention'       => $demandeIntervention,
                'siConsultationApresRenvoi' => $apresRenvoi,
                'dateLimite'                => $dateLimite,
                'equipe'                    => $composantEquipe instanceof Service ? $composantEquipe : $this->getMachineEtat()->getServiceConnecte(),
            ]);
        $this->getMachineEtat()->getMailer()->send($emailMessage);
    }
}
