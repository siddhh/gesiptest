<?php

namespace App\Workflow\Actions;

use App\Entity\Service;
use App\Entity\Demande\HistoriqueStatus;
use App\Form\Demande\Workflow\DonnerAvisCdbType;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class ActionDonnerAvisCdb extends Action
{
    /** @var string NOM */
    public const NOM = "Donner son avis en tant que CDB";

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        return $this->getMachineEtat()->serviceEst(Service::ROLE_GESTION);
    }

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        // On récupère le formulaire
        $form = $this->getMachineEtat()->getFormBuilder(DonnerAvisCdbType::class);

        // On récupère le commentaire saisie lors de la consultation du CDB
        $commentaire = null;
        $donnees = $this->getMachineEtat()->getEtatActuel()->getDonnees();

        if (isset($donnees['commentaire'])) {
            $commentaire = $donnees['commentaire'];
        }

        // On renvoi la vue
        return $this->getMachineEtat()->getTwig()->render('demandes/workflow/donner-avis-cdb.html.twig', [
            'shortClassName' => $this->getShortClassName(),
            'form' => $form->createView(),
            'commentaire' => $commentaire
        ]);
    }

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string
     */
    public function getBoutonLibelle(): string
    {
        return "Donner un avis CDB";
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
     */
    public function traitement(?Request $request = null): JsonResponse
    {
        $form = $this->getMachineEtat()->getFormBuilder(DonnerAvisCdbType::class);
        $form->submit($request->request->all());
        if ($form->isSubmitted() && $form->isValid()) {
            // On met en forme les données pour sauvegarder en base de données
            $formData = $form->getData();
            $donneesAvis = [
                'CDB' => [
                    'serviceId' => $this->getMachineEtat()->getServiceConnecte()->getId(),
                    'avis' => $formData['avis'],
                    'commentaire' => $formData['commentaire'],
                    'date' => (new \DateTime('now'))->format('c')
                ]
            ];

            // On récupère l'état consultation du cdb pour y inscrire l'avis du cdb
            /** @var HistoriqueStatus $historiquesStatusEnCoursCdb */
            $historiquesStatusEnCoursCdb = $this->getMachineEtat()->getEntityManager()->getRepository(HistoriqueStatus::class)->getHistoriqueParEtat($this->getMachineEtat()->getDemandeIntervention(), [ EtatConsultationEnCoursCdb::class ]);
            $historiquesStatusEnCoursCdb = $historiquesStatusEnCoursCdb[0];

            // Si l'utilisateur souhaite envoyer un mail
            if ($formData['envoyerMail']) {
                $this->envoyerMail(
                    $historiquesStatusEnCoursCdb->getAjouteLe(),
                    $this->getMachineEtat()->getServiceConnecte(),
                    $formData['avis'],
                    $formData['commentaire']
                );
            }

            // On sauvegarde les données dans l'historique en cours
            $donneesHistorique = $historiquesStatusEnCoursCdb->getDonnees();
            $historiquesStatusEnCoursCdb->setDonnees(array_merge($donneesHistorique, $donneesAvis));

            // On change l'état
            $this->getMachineEtat()->changerEtat(EtatInstruite::class);

            // On ajoute un message flash
            $this->addFlash("success", "Votre avis sur la demande d'intervention a été pris en compte avec succès.");

            // On envoie notre réponse
            return $this->retourSuccess();
        } else {
            return $this->retourErreur($form);
        }
    }

    /**
     * Méthode permettant d'envoyer le mail lors du traitement
     * @param \DateTimeInterface $dateConsultationCdb
     * @param Service $serviceConnecte
     * @param string $avisCdb
     * @param string $commentaireCdb
     */
    private function envoyerMail(\DateTimeInterface $dateConsultationCdb, Service $serviceConnecte, string $avisCdb, string $commentaireCdb)
    {
        // Construction du mail et envoi
        $machineEtat = $this->getMachineEtat();
        $demandeIntervention = $machineEtat->getDemandeIntervention();
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
            ->subject("{$composantLabel} [GESIP:Décision du CDB] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/donner-un-avis-Cdb.text.twig')
            ->htmlTemplate('emails/demandes/workflow/donner-un-avis-Cdb.html.twig')
            ->context([
                'demandeIntervention'           => $demandeIntervention,    // La demande en cours
                'dateLancementConsultationCdb'  => $dateConsultationCdb,    // Date de lancement de la consulatation du Cdb
                'serviceConnecte'               => $serviceConnecte,        // Service actuellement connecté (donc ayant rempli l'avis Cdb)
                'avisCdb'                       => $avisCdb,                // avis du Cdb (Ok|Ko)
                'commentaireCdb'                => $commentaireCdb          // commentaire avis du Cdb
            ]);
        $machineEtat->getMailer()->send($emailMessage);
    }
}
