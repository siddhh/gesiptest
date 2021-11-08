<?php

namespace App\Workflow\Actions;

use App\Entity\Composant;
use App\Entity\Demande\ImpactReel;
use App\Entity\Demande\SaisieRealise;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use App\Form\Demande\Workflow\SaisieRealiseType;
use App\Form\Demande\Workflow\SaisieRealiseType2;
use App\Workflow\Action;
use App\Workflow\DestinatairesCollection;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;

class ActionSaisirRealise extends Action
{
    /** @var string NOM */
    public const NOM = "Saisir le réalisé";

    const RESULTAT_SUCCESS = 'ok';
    const RESULTAT_FAIL = 'ko';

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        if (parent::peutEtreExecutee()) {
            if (!$this->getMachineEtat()->serviceEst(Service::ROLE_GESTION)) {
                $me = $this->getMachineEtat();
                $serviceConnecteId = $me->getServiceConnecte()->getId();
                return $me->getDemandeIntervention()->isServiceExploitant($me->getServiceConnecte());
            }
            return true;
        }
        return false;
    }

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    public function vue(): ?string
    {
        // On récupère le nécessaire pour commencer à travailler
        $me = $this->getMachineEtat();
        $em = $me->getEntityManager();
        $serviceConnecte = $me->getServiceConnecte();
        $demandeIntervention = $me->getDemandeIntervention();

        // Dispatche les saisies du réalisé précédentes concernant la demande d'intervention courante
        $saisieRealiseServiceConnecte = null;
        $saisieRealisesAutresServices = [];
        foreach ($demandeIntervention->getSaisieRealises() as $saisieRealise) {
            if (!$saisieRealise->getService() || $saisieRealise->getService()->getId() == $serviceConnecte->getId()) {
                $saisieRealiseServiceConnecte = $saisieRealise;
            } else {
                $saisieRealisesAutresServices[] = $saisieRealise;
            }
        }

        // Construction du formulaire à l'aide de la saisie du réalisé du service si existante, sinon à partir d'une nouvelle.
        if (is_null($saisieRealiseServiceConnecte)) {
            $saisieRealiseServiceConnecte = new SaisieRealise();

            // On tranfert les impacts prévisionnels en tant qu'impact réel, pour favoriser la saisie des exploitants
            foreach ($me->getDemandeIntervention()->getImpacts() as $impact) {
                $newImpactReel = new ImpactReel();
                $newImpactReel->setNature($impact->getNature());
                $newImpactReel->setService($serviceConnecte);
                $newImpactReel->setDateDebut($impact->getDateDebut());
                $newImpactReel->setDateFin($impact->getDateFinMax());
                foreach ($impact->getComposants() as $composant) {
                    $newImpactReel->addComposant($composant);
                }
                $saisieRealiseServiceConnecte->addImpactReel($newImpactReel);
            }
        }
        $form = $me->getFormBuilder(SaisieRealiseType::class, $saisieRealiseServiceConnecte, ['data_class' => SaisieRealise::class]);

        // On récupère les références utiles
        $refNaturesImpact = $em->getRepository(NatureImpact::class)
            ->findBy(['supprimeLe' => null], ['label' => 'asc']);
        $refComposants = $em->getRepository(Composant::class)
            ->findBy(['archiveLe' => null], ['label' => 'asc']);

        // on récupère les composants impactés prévus pour la demande d'intervention (pour les afficher par défaut)
        $composantImpactesIds = [];
        foreach ($demandeIntervention->getImpacts() as $impact) {
            foreach ($impact->getComposants() as $composant) {
                $composantImpactesIds[] = $composant->getId();
            }
        }

        // check si on doit afficher un message d'avertissement
        $showWarning = $demandeIntervention->getStatus() == EtatInterventionEnCours::class;

        // enfin on construit et on renvoie la vue
        return $me->getTwig()->render('demandes/workflow/realiser.html.twig', [
            'shortClassName' => $this->getShortClassName(),
            'form'                          => $form->createView(),
            'demandeIntervention'           => $demandeIntervention,
            'refNaturesImpact'              => $refNaturesImpact,
            'refComposants'                 => $refComposants,
            'composantImpacteIds'           => $composantImpactesIds,
            'showWarning'                   => $showWarning,
            'saisieRealiseServiceConnecte'  => $saisieRealiseServiceConnecte,
            'saisieRealisesAutresServices'  => $saisieRealisesAutresServices,
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
     *
     */
    public function traitement(?Request $request = null): JsonResponse
    {
        // On récupère le formulaire et on lui passe les données saisies par l'utilisateur
        $me = $this->getMachineEtat();
        $form = $me->getFormBuilder(SaisieRealiseType2::class);
        $form->submit($request->request->all());

        // On traite notre formulaire si il est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // On récupère les données nécessaires
            $formData = $form->getData();
            $em = $me->getEntityManager();
            $serviceConnecte = $me->getServiceConnecte();
            $serviceConnecteId = $serviceConnecte->getId();
            $demandeIntervention = $me->getDemandeIntervention();

            // Initialise la liste des saisies du réalisé attendues (prevenant de l'annuaire, en excluant le service courant)
            $saisieRealiseAttendueAutreServices = [];
            foreach ($demandeIntervention->getServiceExploitantsArray() as $service) {
                if ($serviceConnecteId != $service->getId()) {
                    $saisieRealiseAttendueAutreServices[$service->getId()] = $service;
                }
            }

            // Dispatche les saisies du réalisé précédentes concernant la demande d'intervention courante
            $saisieRealiseServiceConnecte = null;
            $saisieRealisesGlobalResultatOk = $formData['resultat'] == self::RESULTAT_SUCCESS;
            foreach ($demandeIntervention->getSaisieRealises() as $saisieRealise) {
                if ($saisieRealise->getService()) {
                    $serviceId = $saisieRealise->getService()->getId();
                    if ($serviceId == $serviceConnecteId) {
                        $saisieRealiseServiceConnecte = $saisieRealise;
                    } else {
                        // Si la saisie concerne un service contenu dans la liste des services devant intervenir (exploitants), on la retire des saisies attendues
                        if (isset($saisieRealiseAttendueAutreServices[$serviceId])) {
                            unset($saisieRealiseAttendueAutreServices[$serviceId]);
                        }
                        // On prend en compte le résultat de cette saisie dans le résultat global
                        if ($saisieRealise->getResultat() !== self::RESULTAT_SUCCESS) {
                            $saisieRealisesGlobalResultatOk = false;
                        }
                    }
                } else {
                    $saisieRealiseServiceConnecte = $saisieRealise;
                    $saisieRealiseServiceConnecte->setService($serviceConnecte);
                }
            }

            // Création ou modification de la saisie réalisé du service sur la demande courante
            if (is_null($saisieRealiseServiceConnecte)) {
                $saisieRealiseServiceConnecte = new SaisieRealise();
                $saisieRealiseServiceConnecte->setService($serviceConnecte);
                $demandeIntervention->addSaisieRealise($saisieRealiseServiceConnecte);
                $em->persist($saisieRealiseServiceConnecte);
            } else {
                // Supprime les anciens impacts réels déclarés lors de l'ancienne saisie pour les remplacer par les nouveaux
                foreach ($saisieRealiseServiceConnecte->getImpactReels() as $impactReel) {
                    $saisieRealiseServiceConnecte->removeImpactReel($impactReel);
                    $em->remove($impactReel);
                }
            }
            foreach ($formData['impactReels'] as $impactNumber => $impactReel) {
                $impactReel->setNumeroOrdre($impactNumber + 1);
                $saisieRealiseServiceConnecte->addImpactReel($impactReel);
                $em->persist($impactReel);
            }
            $saisieRealiseServiceConnecte->setCommentaire($formData['commentaire']);
            $saisieRealiseServiceConnecte->setResultat($formData['resultat']);

            // On ferme la demande (même si tous les services n'ont pas encore saisi le réalisé)
            $newStatus = $saisieRealisesGlobalResultatOk
                ? EtatInterventionReussie::class
                : EtatInterventionEchouee::class
            ;
            $me->changerEtat($newStatus);
            // envoi le mail si nécessaire
            $this->envoyerMail(
                $newStatus == EtatInterventionReussie::class ? 'success' : 'fail'
            );

            // commit les modifications en base de données
            $em->flush();
            // On ajoute un message flash
            $this->addFlash("success", "Votre saisie du réalisé à été prise en compte.");
            // retourne une réponse succès
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
        return "Saisir le réalisé";
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
     * @param string $status
     */
    private function envoyerMail(string $status)
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
                DestinatairesCollection::OPTION_SERVICES_COMPOSANT,
                DestinatairesCollection::OPTION_SERVICES_IMPACTES,
            ]
        );
        $emailMessage = (new TemplatedEmail())->from(new Address($this->getParameter('noreply_mail'), $this->getParameter('noreply_mail_label')));
        foreach ($destinatairesCollection->getDestinataires() as $destinataire) {
            $emailMessage->addTo($destinataire);
        }
        $composantLabel = $demandeIntervention->getComposant()->getLabel();
        $demandeDateString = $demandeIntervention->getDateDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/y');
        $emailMessage
            ->subject("{$composantLabel} [GESIP:INTERVENTION " . ($status == 'success' ? "REALISEE AVEC SUCCES" : "EN ECHEC") . "] Intervention programmée {$demandeDateString}")
            ->textTemplate('emails/demandes/workflow/saisir-realise.text.twig')
            ->htmlTemplate('emails/demandes/workflow/saisir-realise.html.twig')
            ->context([
                'demandeIntervention'   => $demandeIntervention,
                'status'                => $status,
            ]);
        $machineEtat->getMailer()->send($emailMessage);
    }
}
