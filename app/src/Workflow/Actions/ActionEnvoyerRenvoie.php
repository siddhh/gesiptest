<?php

namespace App\Workflow\Actions;

use App\Entity\Composant\Annuaire;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Action;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionEnvoyerRenvoie extends Action
{
    /** @var string NOM */
    public const NOM = "Envoyer (après renvoie)";

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
        // Si l'envoi du mail est nécessaire
        if (isset($request->request->get('intervention')['sendMail'])) {
            $machineEtat = $this->getMachineEtat();
            $em = $machineEtat->getEntityManager();
            $demandeIntervention = $machineEtat->getDemandeIntervention();
            $statutsPrecedents = $demandeIntervention->getHistoriqueStatus()->getValues();
            $servicesComposantConcerne = [];
            $servicesComposantsImpactes = [];
            if ($statutsPrecedents[1]->getStatus() != EtatAnalyseEnCours::class) {
                $servicesComposantConcerne = $demandeIntervention->getComposant()->getAnnuaire();
                $consultationEffectuee = false;
                foreach ($statutsPrecedents as $statutPrecedent) {
                    if ($statutPrecedent->getStatus() == EtatConsultationEnCours::class) {
                        $consultationEffectuee = true;
                        foreach (($statutPrecedent->getDonnees())['annuaires'] as $idAnnuaire) {
                            $servicesComposantsImpactes[] = $em->getRepository(Annuaire::class)->find($idAnnuaire);
                        }
                        break;
                    }
                }
                if ($consultationEffectuee == false) {
                    foreach ($demandeIntervention->getComposant()->getComposantsImpactes() as $composantImpacte) {
                        foreach ($composantImpacte->getAnnuaire() as $entreeAnnuaire) {
                            $servicesComposantsImpactes[] = $entreeAnnuaire;
                        }
                    }
                }
            }
            // Envoi du mail proprement dit
            $this->envoyerMail(
                $statutsPrecedents[0]->getDonnees()
            );
        }

        // On change d'état
        $this->getMachineEtat()->changerEtat(EtatAnalyseEnCours::class);

        // On ajoute un message flash
        $this->addFlash("success", "Votre demande corrigée N°{$this->getMachineEtat()->getDemandeIntervention()->getNumero()} a bien été prise en compte.");

        return $this->retourSuccess();
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param array $motifRenvoi
     */
    public function envoyerMail(array $motifRenvoi)
    {
        // Construction de la liste des destinataires
        $machineEtat = $this->getMachineEtat();
        $demandeIntervention = $machineEtat->getDemandeIntervention();
        $destinatairesCollection = new DestinatairesCollection(
            $demandeIntervention,
            [
                DestinatairesCollection::OPTION_DEMANDEUR,
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
            ]
        );
        // Change le mail en fonction du contexte de renvoi
        $contexte = null;
        $demandeHistoriqueStatus = $demandeIntervention->getHistoriqueStatus();
        $statusPrecedent = !empty($demandeHistoriqueStatus[2]) ? $demandeHistoriqueStatus[2]->getStatus() : null;
        switch ($statusPrecedent) {
            case EtatConsultationEnCours::class:
                $contexte = 'CONSULTATION';
                $destinatairesCollection->ajouteServicesConsultes();
                $destinatairesCollection->ajouteIntervenants();
                break;
            case EtatInstruite::class:
                $contexte = 'ACCORD DE LA DME';
                $destinatairesCollection->ajouteServicesConsultes();
                break;
            default:
                $contexte = 'ANALYSE';
        }
        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = (clone $demandeIntervention->getDateDebut())->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:CORRECTION] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/envoyerApresRenvoie.text.twig')
            ->htmlTemplate('emails/demandes/workflow/envoyerApresRenvoie.html.twig')
            ->context([
                'demandeIntervention'   => $demandeIntervention,
                'motifsRenvoi'          => $motifRenvoi,
                'contexte'              => $contexte
            ]);
        $machineEtat->getMailer()->send($emailMessage);
    }
}
