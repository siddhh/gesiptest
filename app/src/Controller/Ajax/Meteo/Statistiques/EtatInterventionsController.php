<?php

namespace App\Controller\Ajax\Meteo\Statistiques;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\DemandeIntervention;
use App\Entity\Service;

class EtatInterventionsController extends AbstractController
{
    /**
     * @Route(
     *      "/ajax/meteo/statistiques/etat-interventions/{anneeDebut}/{anneeFin}",
     *      methods={"GET"},
     *      name="ajax-meteo-statistiques-etat_interventions",
     * )
     */
    public function etatGlobalInterventions(int $anneeDebut, int $anneeFin, Request $request): JsonResponse
    {
        // on traite les paramètres transmis par la requête
        $parBureauRattachement = $request->query->get('bureau');
        $tz = new \DateTimeZone('Europe/Paris');
        $periodeDebut = \DateTime::createFromFormat('Y-m-d H:i:s', $anneeDebut . "-01-01 00:00:00", $tz);
        $periodeFin = \DateTime::createFromFormat('Y-m-d H:i:s', $anneeFin . "-12-31 23:59:59", $tz);

        // on récupère en base la liste des demandes d'intervention concernées par la requête
        $listeDemandes = $this->getDoctrine()->getRepository(DemandeIntervention::class)->listeInterventionsEtatGlobal($periodeDebut, $periodeFin, $parBureauRattachement);

        // on initialise la réponse
        $reponse = [
            'annees' => [],
            'references' => [],
            'comptage' => []
        ];
        // reponse['annees'] contient la liste des années de la période concernée
        foreach (range($anneeDebut, $anneeFin) as $annee) {
            array_push($reponse['annees'], strval($annee));
        }

        if ($parBureauRattachement == "oui") {
            // agrégation par bureau de rattachement
            // reponse['references'] contient la liste des bureaux de rattachement
            // reponse['comptage'] contient 1 élément par bureau de rattachement contenant 1 compteur par année
            $listeBureaux = $this->getDoctrine()->getRepository(Service::class)->findBy(['estBureauRattachement' => true], ['label' => 'asc']);
            foreach ($listeBureaux as $bureau) {
                $bureauId = $bureau->getId();
                $reponse['references'][$bureauId] = $bureau->getLabel();
                $reponse['comptage'][$bureauId] = [];
                foreach ($reponse['annees'] as $annee) {
                    $reponse['comptage'][$bureauId][$annee] = 0;
                }
            }

            foreach ($listeDemandes as $demande) {
                $annee = max($anneeDebut, intval($demande->getDateDebut()->setTimezone($tz)->format("Y")));
                $plafondAnnee = min($anneeFin, intval($demande->getDateFinMax()->setTimezone($tz)->format("Y")));
                do {
                    ++$reponse['comptage'][$demande->getComposant()->getBureauRattachement()->getId()][$annee];
                    ++$annee;
                } while ($annee <= $plafondAnnee);
            }
        } else {
            // agrégation par mois
            // reponse['references'] contient la liste des mois de l'année
            // reponse['comptage'] contient 1 élément par mois contenant 1 compteur par année
            $reponse['references'] = [
                '1' => 'Janvier',
                '2' => 'Février',
                '3' => 'Mars',
                '4' => 'Avril',
                '5' => 'Mai',
                '6' => 'Juin',
                '7' => 'Juillet',
                '8' => 'Août',
                '9' => 'Septembre',
                '10' => 'Octobre',
                '11' => 'Novembre',
                '12' => 'Décembre'
            ];
            foreach (range(1, 12) as $mois) {
                $reponse['comptage'][$mois] = [];
                foreach ($reponse['annees'] as $annee) {
                    $reponse['comptage'][$mois][$annee] = 0;
                }
            }

            foreach ($listeDemandes as $demande) {
                $seuilDate = max($demande->getDateDebut()->setTimezone($tz), $periodeDebut);
                $plafondDate = min($demande->getDateFinMax()->setTimezone($tz), $periodeFin);
                $annee = intval($seuilDate->format("Y"));
                $mois = intval($seuilDate->format("n"));
                $plafondAnnee = intval($plafondDate->format("Y"));
                $plafondMois = intval($plafondDate->format("n"));
                do {
                    ++$reponse['comptage'][$mois][$annee];
                    ++$mois;
                    if (($mois == 13) && ($annee != $plafondAnnee)) {
                        $mois = 1;
                        ++$annee;
                    }
                } while (($annee != $plafondAnnee) || ($mois <= $plafondMois));
            }
        }

        return new JsonResponse($reponse);
    }
}
