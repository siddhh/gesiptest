<?php

namespace App\Controller\Ajax;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Composant;

class ComposantController extends AbstractController
{

    /**
     * @Route(
     *      "/ajax/composant/recherche/",
     *      methods={"POST"},
     *      name="ajax-composant-recherche"
     * )
     */
    public function listeComposants(Request $request)
    {
        $tableauRecherche = [
            'label'         => $request->get('Label'),
            'equipeId'      => $request->get('Equipe'),
            'piloteId'      => $request->get('Pilote'),
            'exploitantId'  => $request->get('Exploitant'),
            'usagerId'      => $request->get('Usager'),
            'domaineId'     => $request->get('Domaine'),
            'isArchived'    => $request->get('IsArchived')
        ];

        $resultat = $this->getDoctrine()
            ->getRepository(Composant::class)
            ->listeComposants($tableauRecherche);

        $reponse = [
            'recherche' => 'liste',
            'donnees' => []
        ];

        foreach ($resultat as $composant) {
            $retComposant = [
                'id' => $composant->getId(),
                'label' => $composant->getLabel(),
                'usager_id' => $composant->getUsager()->getLabel(),
                'archive_le' => $composant->getarchiveLe()
            ];
            if ($equipe = $composant->getEquipe()) {
                $retComposant['equipe'] = [
                    'id'    => $equipe->getId(),
                    'label' => $equipe->getLabel(),
                ];
            }
            if ($pilote = $composant->getPilote()) {
                $retComposant['pilote'] = [
                    'id'                => $pilote->getId(),
                    'nom_complet_court' => $pilote->getNomCompletCourt(),
                ];
            }
            if ($exploitant = $composant->getExploitant()) {
                $retComposant['exploitant'] = [
                    'id'    => $exploitant->getId(),
                    'label' => $exploitant->getLabel(),
                ];
            }
            if ($domaine = $composant->getDomaine()) {
                $retComposant['domaine'] = [
                    'id'    => $domaine->getId(),
                    'label' => $domaine->getLabel(),
                ];
            }
            $reponse['donnees'][] = $retComposant;
        }
        return new JsonResponse($reponse);
    }

    /**
     * @Route("/ajax/composant/recherche/label", methods={"GET"}, name="ajax-composant-recherche-label")
     */
    public function rechercheComposantParLabel(Request $request): JsonResponse
    {
        $labelSearch = $request->query->get("label");
        $composants = $this->getDoctrine()
            ->getRepository(Composant::class)
            ->searchByLabel($labelSearch);
        return new JsonResponse($composants);
    }

    /**
     * @Route("/ajax/composant/{id}/annuaire", methods={"GET"}, name="ajax-composant-annuaire")
     */
    public function recuperationAnnuaireDuComposantParMission(Request $request, Composant $composant): JsonResponse
    {
        // On initialise $donnees et on récupère les annuaires correspondants au composant passé en paramètre
        //  (mode: uniquement ceux qui peuvent intervenir sur le composant)
        $donnees = [];
        $missions = [];
        $donneesTemporaires = [];
        $annuaires = $this->getDoctrine()->getRepository(Composant\Annuaire::class)
            ->annuaireParComposants($composant, true);

        // On parcourt une première fois les annuaires
        foreach ($annuaires as $annuaire) {
            // Si le tableau de mapping IDMission => LabelMission ne possède pas encore la mission,
            //  alors on l'ajoute dans le tableau dédié.
            if (!isset($missions[$annuaire->getMission()->getId()])) {
                $missions[$annuaire->getMission()->getId()] = $annuaire->getMission()->getLabel();
            }
            // Si le tableau temporaire possède déjà le service pour une mission, ou si la balf est la même que celle
            // du service associé, alors on l'ajoute dans le tableau temporaire.
            // (nous sommes donc sûr d'avoir qu'un seul service par mission et au mieux un annuaire possédant la balf par défaut d'un service)
            if (!isset($donneesTemporaires[$annuaire->getMission()->getId()][$annuaire->getService()->getId()]) || $annuaire->getBalf() === $annuaire->getService()->getEmail()) {
                $donneesTemporaires[$annuaire->getMission()->getId()][$annuaire->getService()->getId()] = $annuaire;
            }
        }

        // On parcourt notre tableau temporaire pour formater la réponse à renvoyer. (2 niveaux : missions puis services)
        foreach ($donneesTemporaires as $missionId => $annuaires) {
            foreach ($annuaires as $annuaire) {
                $service = $annuaire->getService();
                $donnees[$missions[$missionId]][$annuaire->getId()] = [
                    'id'    => $service->getId(),
                    'label' => $service->getLabel()
                ];
            }
        }

        // On retourne notre réponse au navigateur
        return new JsonResponse($donnees);
    }

    /**
     * @Route("/ajax/composant/{id}/flux-sortants", methods={"GET"}, name="ajax-composant-flux-sortants")
     */
    public function recuperationImpactDuComposant(Composant $composant): JsonResponse
    {
        $donnees = [];
        /** @var Composant $composantImpacte */
        foreach ($composant->getFluxSortants(false) as $composantImpacte) {
            $donnees[] = [
                'id' => $composantImpacte->getId(),
                'label' => $composantImpacte->getLabel()
            ];
        }
        return new JsonResponse($donnees);
    }
}
