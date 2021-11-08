<?php

namespace App\Controller;

use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\DemandeIntervention;
use App\Entity\Composant\Annuaire;

class NotificationsController extends AbstractController
{

    /**
     * Fonction permettant de récupérer les notifications à afficher dans le header de la page.
     * (appelée dans le template 'base.pleine.html.twig')
     * @return Response
     */
    public function afficher(): Response
    {
        // On initialise quelques variables utiles
        $em = $this->getDoctrine();
        $notifications = [];

        /** Service $serviceCourant */
        $serviceCourant = $this->getUser();
        $serviceEstAdmin = in_array('ROLE_ADMIN', $serviceCourant->getRoles());
        $serviceEstDME = in_array('ROLE_DME', $serviceCourant->getRoles());

        // Récupérations du nombre de demandes en attente pour les flux
        $nbDemandesFlux = $em->getRepository(DemandeReferentielFlux::class)
            ->nombreDemandesEnAttente($serviceEstAdmin ? null : $serviceCourant);
        // Récupération du nombre de demandes en attente pour le périmètre applicatif
        $nbDemandesApplicatif = $em->getRepository(DemandePerimetreApplicatif::class)
            ->nombreDemandesEnAttente($serviceEstAdmin ? null : $serviceCourant);

        // Si une demande existe, alors on ajoute l'information dans l'array de notifications
        if ($nbDemandesFlux > 0 || $nbDemandesApplicatif > 0) {
            // Initialisation de quelques variables
            $textes = [];
            $formatMessage = '<a href="%s">%s demande%s concernant %s</a>';

            // Si il y a des demandes concernant les flux, on met en forme le texte
            if ($nbDemandesFlux > 0) {
                $textes[] = sprintf(
                    $formatMessage,
                    $this->generateUrl(($serviceEstAdmin || $serviceEstDME ? 'gestion-fiabilisation-flux-liste' : 'fiabilisation-flux-index-demandes')),
                    $nbDemandesFlux,
                    ($nbDemandesFlux > 1 ? 's' : ''),
                    ($serviceEstAdmin || $serviceEstDME ? 'leur référentiel des flux' : 'votre référentiel des flux')
                );
            }

            // Si il y a des demandes concernant l'applicatif, on met en forme le texte
            if ($nbDemandesApplicatif > 0) {
                $textes[] = sprintf(
                    $formatMessage,
                    $this->generateUrl(($serviceEstAdmin || $serviceEstDME ? 'gestion-fiabilisation-applicatif-liste' : 'fiabilisation-applicatif-demandes')),
                    $nbDemandesApplicatif,
                    ($nbDemandesApplicatif > 1 ? 's' : ''),
                    ($serviceEstAdmin || $serviceEstDME ? 'leur périmètre applicatif' : 'votre périmètre applicatif')
                );
            }

            // On ajoute l'information dans le tableau
            $notifications[] = [
                'titre' =>
                    ($serviceEstAdmin ? 'Des services ont fait des demandes de mise à jour' : 'Demande de mise à jour en attente'),
                'texte' => $textes,
                'href' => null
            ];
        }

        // recherche des demandes d'intervention pour lesquelles le service est consulté
        $listeAnnuaire = $em->getRepository(Annuaire::class)->findby(['service' => $serviceCourant]);
        if (count($listeAnnuaire) > 0) {
            $annuaireId = [];
            foreach ($listeAnnuaire as $entreeAnnuaire) {
                $annuaireId[] = $entreeAnnuaire->getId();
            }
            $listeDemandesInterventions = $em->getRepository(DemandeIntervention::class)->listeDemandesInterventionsPourAvis($annuaireId);
            if (count($listeDemandesInterventions) > 0) {
                $textes = [];
                $formatMessage = '<a href="%s">%s  - Intervention du  %s</a>';

                foreach ($listeDemandesInterventions as $demandeIntervention) {
                    // on teste si le service n'a pas déjà répondu à la demande
                    if ($demandeIntervention[1] == null ||
                        $demandeIntervention[1] == "[]" ||
                        !property_exists(json_decode($demandeIntervention[1]), $serviceCourant->getId())
                    ) {
                        $textes[] = sprintf(
                            $formatMessage,
                            $this->generateUrl('demandes-visualisation', ['id' => $demandeIntervention[0]->getId()]),
                            $demandeIntervention[0]->getComposant()->getLabel(),
                            $demandeIntervention[0]->getDateDebut()->format('d/m/Y')
                        );
                    }
                }

                // On ajoute l'information dans le tableau
                if (count($textes) > 0) {
                    $notifications[] = [
                        'titre' => 'Vous avez des demandes pour lesquelles votre avis est souhaité',
                        'texte' => $textes,
                        'href' => null
                    ];
                }
            }
        }

        // On renvoi la vue rendue par twig
        return $this->render('notifications.html.twig', compact('notifications'));
    }
}
