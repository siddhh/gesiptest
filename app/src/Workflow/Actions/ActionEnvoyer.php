<?php

namespace App\Workflow\Actions;

use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionEnvoyer extends Action
{
    /** @var string NOM */
    public const NOM = "Envoyer";

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
        // envoi mail
        if (isset($request->request->get('intervention')['sendMail'])) {
            $this->envoyerMail();
        }

        // changement d'état
        $this->getMachineEtat()->changerEtat(EtatAnalyseEnCours::class);

        // On ajoute un message flash
        $this->addFlash("success", "Votre demande N°{$this->getMachineEtat()->getDemandeIntervention()->getNumero()} a bien été prise en compte.");

        return $this->retourSuccess();
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
                DestinatairesCollection::OPTION_DEMANDEUR,
                DestinatairesCollection::OPTION_ADMINS,
                DestinatairesCollection::OPTION_PILOTE_EQUIPE_OU_DME,
            ]
        );
        // Construction du mail et envoi
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDate = clone $demandeIntervention->getDateDebut();
        $demandeDateString = $demandeDate->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:DEMANDE D'INTERVENTION PROGRAMMEE] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/envoyer.text.twig')
            ->htmlTemplate('emails/demandes/workflow/envoyer.html.twig')
            ->context([
                'demandeIntervention' => $demandeIntervention,
            ]);
        $this->getMachineEtat()->getMailer()->send($emailMessage);
    }
}
