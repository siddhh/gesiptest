<?php

namespace App\Controller\Ajax\Meteo;

use App\Entity\Meteo\Publication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DatepickerController extends AbstractController
{
    /**
     * @Route("/ajax/meteo/datepicker/periodes-publiees", methods={"GET"}, name="ajax-meteo-datepicker-periodes-publiees")
     */
    public function periodesPubliees(Request $request): JsonResponse
    {
        // On récupère la date du jour, ainsi que les informations saisies par l'utilisateur
        //  (par défaut => mois et année en cours)
        $now = new \DateTime();
        $mois = (int)$request->get('month', $now->format('m'));
        $annee = (int)$request->get('year', $now->format('Y'));

        // On récupère les périodes déjà publiées du mois
        $joursPeriodesPubliees = $this->getDoctrine()->getManager()->getRepository(Publication::class)
            ->joursPeriodesPubliees($annee, $mois);

        // On retourne la liste des jours
        return JsonResponse::create($joursPeriodesPubliees);
    }

    /**
     * @Route("/ajax/meteo/datepicker/periodes-a-saisir/{action}", defaults={"action"="publication"}, methods={"GET"}, name="ajax-meteo-datepicker-periodes-a-saisir")
     */
    public function periodesASaisir(string $action, Request $request): JsonResponse
    {
        // On récupère la date du jour, ainsi que les informations saisies par l'utilisateur
        //  (par défaut => mois et année en cours)
        $now = new \DateTime();
        $mois = (int)$request->get('month', $now->format('m'));
        $annee = (int)$request->get('year', $now->format('Y'));
        $dates = [];

        // On récupère le nombre de jours du mois
        $nbJours = cal_days_in_month(CAL_GREGORIAN, $mois, $annee);

        // On récupère les jours des périodes déjà publiées du mois
        $joursPeriodesPubliees = $this->getDoctrine()->getManager()->getRepository(Publication::class)
            ->joursPeriodesPubliees($annee, $mois);

        // On boucle sur les jours du mois
        for ($d = 1; $d <= $nbJours; $d++) {
            $stringDate = $annee . '-' . str_pad($mois, 2, "0", STR_PAD_LEFT) . '-' . str_pad($d, 2, "0", STR_PAD_LEFT);
            $datetimeDate = \DateTime::createFromFormat('Y-m-d', $stringDate);

            switch ($action) {
                case 'depublication':
                    if (in_array($stringDate, $joursPeriodesPubliees) && $datetimeDate <= $now) {
                        $dates[] = $stringDate;
                    }
                    break;
                default:
                    if (!in_array($stringDate, $joursPeriodesPubliees) && $datetimeDate <= $now) {
                        $dates[] = $stringDate;
                    }
            }
        }

        // On retourne la liste des jours
        return JsonResponse::create($dates);
    }
}
