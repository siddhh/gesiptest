<?php

namespace App\Controller\Calendrier;

use App\Entity\MepSsi;
use App\Entity\DemandeIntervention;
use App\Entity\Operation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\Calendrier\VueInterApplicativeType;

class InterApplicatifController extends AbstractController
{
    /**
     * @Route("/calendrier/inter-applicatif", name="calendrier-vue-inter-applicatif")
     */
    public function vueInterApplicative(Request $request): Response
    {
        // Initialisations
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(VueInterApplicativeType::class);
        $aujourdhui = (new \DateTime())->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
        $listeFluxEntrant = null;
        $fluxEntrant = [];
        $listeFluxSortant = null;
        $fluxSortant = [];
        $composantDemande = null;
        $referentielComposants = [];
        $referentielDomaines = [];

        // Traitement du formulaire
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $composantDemande = $data['composant'];

            // On récupère les composants impactés et impactants et leur domaine respectif,
            //  que l'on ajoute dans notre référentiel composants / domaines
            foreach ($composantDemande->getImpactesParComposants() as $composant) {
                $fluxEntrant[] = $composant->getId();
                $referentielComposants[$composant->getId()] = $composant->getLabel();
                if ($composant->getDomaine()) {
                    $referentielDomaines[$composant->getDomaine()->getId()] = $composant->getDomaine()->getLabel();
                }
            }

            foreach ($composantDemande->getComposantsImpactes() as $composant) {
                $fluxSortant[] = $composant->getId();
                $referentielComposants[$composant->getId()] = $composant->getLabel();
                if ($composant->getDomaine()) {
                    $referentielDomaines[$composant->getDomaine()->getId()] = $composant->getDomaine()->getLabel();
                }
            }

            // On récupère les demandes d'intervention ainsi que les MEP SSI
            $listeDemandesIntervention = $em->getRepository(DemandeIntervention::class)->listePourVueInterApplicative(array_keys($referentielComposants));
            $listeMepSsi = $em->getRepository(MepSsi::class)->listePourVueInterApplicative(array_keys($referentielComposants));

            // On parcourt nos MepSsi
            foreach ($listeMepSsi as $mepSsi) {
                $operation = new Operation($mepSsi);

                foreach ($mepSsi->getComposants() as $composant) {
                    $idComposant = $composant->getId();
                    $idDomaine = ($composant->getDomaine() == null ? 'Aucun domaine' : $composant->getDomaine()->getId());

                    $description = [
                        'label'      => sprintf(
                            'MEP SSI N°%s du %s',
                            $mepSsi->getId(),
                            ($operation->getInterventionFin()->format('d/m/Y') === $operation->getInterventionDebut()->format('d/m/Y')) ? $operation->getInterventionDebut()->setTimeZone(new \DateTimeZone('Europe/paris'))->format('d/m/Y') :
                                $operation->getInterventionDebut()->setTimeZone(new \DateTimeZone('Europe/paris'))->format('d/m/Y') . ' au ' . $operation->getInterventionFin()->setTimeZone(new \DateTimeZone('Europe/paris'))->format('d/m/Y')
                        ),
                        'lien'       => $this->generateUrl('calendrier-mepssi-consulter', ['mepSsi' => $mepSsi->getId()]),
                        'aujourdhui' => $operation->getInterventionDebutTz() >= $aujourdhui->setTime(0, 0, 0) && $operation->getInterventionFinTz() <= $aujourdhui->setTime(23, 59, 59)
                    ];

                    if (in_array($composant->getId(), $fluxEntrant)) {
                        $listeFluxEntrant[$idDomaine][$idComposant][] = $description;
                    }

                    if (in_array($composant->getId(), $fluxSortant)) {
                        $listeFluxSortant[$idDomaine][$idComposant][] = $description;
                    }
                }
            }

            // On parcourt nos demandes d'interventions
            foreach ($listeDemandesIntervention as $demandeIntervention) {
                $operation = new Operation($demandeIntervention);

                $idComposant = $demandeIntervention->getComposant()->getId();
                $idDomaine = ($demandeIntervention->getComposant()->getDomaine() == null ? 'Aucun domaine' : $demandeIntervention->getComposant()->getDomaine()->getId());

                $description = [
                    'label'      => sprintf(
                        'Intervention N°%s du %s',
                        $demandeIntervention->getNumero(),
                        ($operation->getInterventionFin()->format('d/m/Y') === $operation->getInterventionDebut()->format('d/m/Y')) ? $operation->getInterventionDebut()->setTimeZone(new \DateTimeZone('Europe/paris'))->format('d/m/Y') :
                            $operation->getInterventionDebut()->setTimeZone(new \DateTimeZone('Europe/paris'))->format('d/m/Y') . ' au ' . $operation->getInterventionFin()->setTimeZone(new \DateTimeZone('Europe/paris'))->format('d/m/Y')
                    ),
                    'lien'       => $this->generateUrl('demandes-visualisation', ['id' => $demandeIntervention->getId()]),
                    'aujourdhui' => $operation->getInterventionDebutTz() >= $aujourdhui->setTime(0, 0, 0) && $operation->getInterventionFinTz() <= $aujourdhui->setTime(23, 59, 59)
                ];

                if (in_array($demandeIntervention->getComposant()->getId(), $fluxEntrant)) {
                    $listeFluxEntrant[$idDomaine][$idComposant][] = $description;
                }

                if (in_array($demandeIntervention->getComposant()->getId(), $fluxSortant)) {
                    $listeFluxSortant[$idDomaine][$idComposant][] = $description;
                }
            }

            // On tri nos référentiels Domaines et Composants par ordre alphabétique
            asort($referentielDomaines);
            asort($referentielComposants);
        }

        // Construction de la réponse
        return $this->render('calendrier/vue-inter-applicative.html.twig', [
            'form'                  => $form->createView(),
            'composant'             => $composantDemande,
            'referentielComposants' => $referentielComposants,
            'referentielDomaines'   => $referentielDomaines,
            'listeFluxEntrant'      => $listeFluxEntrant,
            'listeFluxSortant'      => $listeFluxSortant
        ]);
    }
}
