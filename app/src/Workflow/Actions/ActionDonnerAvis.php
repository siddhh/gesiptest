<?php

namespace App\Workflow\Actions;

use App\Entity\Service;
use App\Entity\Composant\Annuaire;
use App\Entity\Demande\HistoriqueStatus;
use App\Form\Demande\Workflow\DonnerAvisType;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatConsultationEnCours;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionDonnerAvis extends Action
{
    /** @var string NOM */
    public const NOM = "Donner son avis";

    const AVIS = 'avis';

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        // Récupère la liste des services consultés
        $me = $this->getMachineEtat();
        $serviceConnecte = $me->getServiceConnecte();
        $serviceConnecteId = $serviceConnecte ? $serviceConnecte->getId() : "Invite";
        $statusDonnees = $me->getDemandeIntervention()->getStatusDonnees();
        $annuaireIds = !empty($statusDonnees[ActionLancerConsultation::ANNUAIRES]) ? $statusDonnees[ActionLancerConsultation::ANNUAIRES] : [];
        $annuairesConsultes = $me->getEntityManager()->getRepository(Annuaire::class)->findBy(['id' => $annuaireIds]);
        foreach ($annuairesConsultes as $annuaire) {
            if ($serviceConnecteId == $annuaire->getService()->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        $form = $this->getMachineEtat()->getFormBuilder(DonnerAvisType::class);

        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/donner-avis.html.twig', [
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
        $form = $me->getFormBuilder(DonnerAvisType::class);
        $form->submit($request->request->all());
        $serviceConnecte = $me->getServiceConnecte();
        if ($serviceConnecte !== null && $form->isSubmitted() && $form->isValid()) {
            $em = $me->getEntityManager();
            $formData = $form->getData();
            $demandeIntervention = $me->getDemandeIntervention();
            // Récupération des annuaires précédemment selectionnés pour consultation
            $statusDonnees = $demandeIntervention->getStatusDonnees();
            $annuaireIds = !empty($statusDonnees[ActionLancerConsultation::ANNUAIRES]) ? $statusDonnees[ActionLancerConsultation::ANNUAIRES] : [];
            $avisServices = !empty($statusDonnees[self::AVIS]) ? $statusDonnees[self::AVIS] : [];
            $annuairesConsultes = $me->getEntityManager()->getRepository(Annuaire::class)->findBy(['id' => $annuaireIds]);

            // envoi mail si nécessaire
            if ($formData['avis'] == 'ko' && $formData['envoyerMail']) {
                $this->envoyerMail($serviceConnecte, $formData['avis'], $formData['commentaire']);
            }

            // rafraichissement de l'état de la demande et dans l'historique
            $avisServices[$serviceConnecte->getId()] = [
                'avis'          => $formData['avis'],
                'commentaire'   => $formData['commentaire'],
                'date'          => (new \DateTime('now'))->format('c')
            ];
            $statusDonnees[self::AVIS] = $avisServices;
            $demandeIntervention->setStatusDonnees($statusDonnees);
            $derniersHistoriqueStatus = $em->getRepository(HistoriqueStatus::class)->getHistoriqueParEtat($demandeIntervention, [ EtatConsultationEnCours::class ]);
            if (($historiqueStatus = reset($derniersHistoriqueStatus)) !== false) {
                $historiqueStatus->setDonnees($statusDonnees);
            }

            // On ajoute un message flash
            $this->addFlash("success", "Votre avis sur la demande d'intervention a été pris en compte avec succès.");

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
        return "Donner un avis";
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
     * @param Service $serviceConnecte
     * @param string $avis
     * @param string $commentaire
     */
    private function envoyerMail(Service $serviceConnecte, string $avis, string $commentaire)
    {
        // Construction du mail et envoi
        $machineEtat = $this->getMachineEtat();
        $demandeIntervention = $machineEtat->getDemandeIntervention();
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_DEMANDEUR,
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_INTERVENANTS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
                DestinatairesCollection::OPTION_SERVICES_CONSULTES,
            ]
        );
        $destinatairesCollection->excluDestinataires([$machineEtat->getServiceConnecte()]);

        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:CONSULTATION/AVIS DEFAVORABLE] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/donner-avis.text.twig')
            ->htmlTemplate('emails/demandes/workflow/donner-avis.html.twig')
            ->context([
                'demandeIntervention' => $demandeIntervention,
                'service'               => $serviceConnecte,
                'avis'                  => $avis,
                'commentaire'           => $commentaire,
            ]);
            $machineEtat->getMailer()->send($emailMessage);
    }
}
