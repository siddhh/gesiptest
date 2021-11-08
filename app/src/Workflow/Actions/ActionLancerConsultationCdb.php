<?php

namespace App\Workflow\Actions;

use App\Entity\Service;
use App\Form\Demande\Workflow\ConsulterCdbType;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionLancerConsultationCdb extends Action
{
    /** @var string NOM */
    public const NOM = "Lancer la consultation du CDB";

    /**
     * Renvoie True, si l'action est exécutable.
     * (Si l'utilisateur courant est ROLE_GESTION et si "Avec consultation du Chef De Bureau" est coché)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        $estRoleGestion  = $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
        $statusDonnees = $this->getMachineEtat()->getDemandeIntervention()->getStatusDonnees();
        if (parent::peutEtreExecutee() && $estRoleGestion && $statusDonnees['avecCdb']) {
            return true;
        } else {
            return false;
        }
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
        $form = $this->getMachineEtat()->getFormBuilder(ConsulterCdbType::class);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/consulterCdb.html.twig', [
            'shortClassName' => $this->getShortClassName(),
            'form' => $form->createView()
        ]);
    }

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Consulter le CDB";
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

        $form = $this->getMachineEtat()->getFormBuilder(ConsulterCdbType::class);
        $form->submit($request->request->all());

        if ($form->isSubmitted() && $form->isValid()) {
            // On met en forme les données pour sauvegarder en base de données
            $formData = $form->getData();

            // Si "avec envoi de mail" est coché, alors on envoi un mail
            if ($formData['envoyerMail']) {
                $this->envoyerMail();
            }

            // On change l'état
            $this->getMachineEtat()->changerEtat(EtatConsultationEnCoursCdb::class, [ 'commentaire' => $formData['commentaire'] ]);

            // On ajoute un message flash
            $this->addFlash("success", "La consultation auprès du Chef De Bureau a été lancée avec succès.");

            // On envoie notre réponse
            return $this->retourSuccess();
        } else {
            return $this->retourErreur($form);
        }
    }


    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     */
    private function envoyerMail()
    {
        // Construction du mail et envoi
        $demandeIntervention = $this->getMachineEtat()->getDemandeIntervention();
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
                DestinatairesCollection::OPTION_SI2A
            ]
        );
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:Pour décision CDB] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/consulterCdb.text.twig')
            ->htmlTemplate('emails/demandes/workflow/consulterCdb.html.twig')
            ->context([
                'demandeIntervention' => $demandeIntervention
            ]);
        $this->getMachineEtat()->getMailer()->send($emailMessage);
    }
}
