<?php

namespace App\Controller\Calendrier;

use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Entity\Operation;
use App\Entity\Pilote;
use App\Service\OperationService;
use App\Utils\I18nDate;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class GlobalController extends AbstractController
{
    /**
     * On défini quelques constantes permettant de représenter les différentes types de vue possible.
     */
    const VUE_PERIODE_7JOURS = '7jours';
    const VUE_PERIODE_JOURS = 'jours';
    const VUE_PERIODE_SEMAINES = 'semaines';
    const VUE_PERIODE_MOIS = 'mois';
    const VUE_PERIODE_MOINS_90JOURS = '-90jours';
    const VUE_PERIODE_DANS_120JOURS = '120jours';
    const VUE_TYPE_TABLEAU = 'format-tableau';
    const VUE_TYPE_CALENDRIER = 'format-calendrier';

    /**
     * Permet de récupérer un tableau permettant d'afficher le système de navigation dans la période donnée
     * et la vue demandée.
     *
     * @param \DateTime $dateDebut
     * @param string    $vuePeriode
     *
     * @return array|null
     * @throws \Exception
     */
    private function getPeriodeNavigationArray(\DateTime $dateDebut, string $vuePeriode): ?array
    {
        $navigation = null;

        switch ($vuePeriode) {
            case self::VUE_PERIODE_JOURS:
                $navigation = [];
                $interval = 'D';
                $formatActif = 'l j F Y';
                $formatNonActif = 'd';
                break;
            case self::VUE_PERIODE_SEMAINES:
                $navigation = [];
                $interval = 'W';
                $formatActif = '\S\e\m\a\i\n\e \n\°W (Y)';
                $formatNonActif = 'W';
                break;
            case self::VUE_PERIODE_MOIS:
                $navigation = [];
                $interval = 'M';
                $formatActif = 'F Y';
                $formatNonActif = 'M';
                break;
        }

        if ($navigation !== null) {
            for ($iperiode = -5; $iperiode <= 5; $iperiode++) {
                if ($iperiode < 0) {
                    $calculDate = (clone $dateDebut)->sub(new \DateInterval('P' . abs($iperiode) . $interval));
                } elseif ($iperiode == 0) {
                    $calculDate = $dateDebut;
                } elseif ($iperiode > 0) {
                    $calculDate = (clone $dateDebut)->add(new \DateInterval('P' . abs($iperiode) . $interval));
                }

                $navigation[] = [
                    'label' => $iperiode == 0 ? I18nDate::__($formatActif, $calculDate) : I18nDate::__($formatNonActif, $calculDate),
                    'date' => $calculDate->format('Y-m-d'),
                ];
            }
        }

        return $navigation;
    }

    /**
     * Permet de récupérer le type d'affichage ainsi que la date de début et de fin de la période à afficher.
     *
     * @param \DateTime      $dateDebut
     * @param string         $vuePeriode
     * @param string|null    &$type
     * @param \DateTime|null &$periodeDebut
     * @param \DateTime|null &$periodeFin
     * @param array          &$periodeRange
     */
    private function getTypePeriodeAffichage(\DateTime $dateDebut, string $vuePeriode, ?string &$type, ?\DateTime &$periodeDebut, ?\DateTime &$periodeFin, array &$periodeRange): void
    {
        $type = $periodeDebut = $periodeFin = null;
        $periodeRange = [];

        switch ($vuePeriode) {
            case self::VUE_PERIODE_7JOURS:
                $type = self::VUE_TYPE_TABLEAU;
                $periodeDebut = $dateDebut;
                $periodeFin = (clone $dateDebut)->add(new \DateInterval('P7D'));
                $periodeDebutRange = $periodeDebut;
                $periodeFinRange = (clone $periodeFin);
                break;
            case self::VUE_PERIODE_JOURS:
                $type = self::VUE_TYPE_TABLEAU;
                $periodeDebut = (clone $dateDebut);
                $periodeFin = (clone $dateDebut);
                $periodeDebutRange = $periodeDebut;
                $periodeFinRange = (clone $periodeFin);
                break;
            case self::VUE_PERIODE_SEMAINES:
                $type = self::VUE_TYPE_CALENDRIER;
                $periodeDebut = ($dateDebut->format('N') == 1) ? (clone $dateDebut) : (clone $dateDebut)->modify('last monday');
                $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'));
                $periodeDebutRange = $periodeDebut;
                $periodeFinRange = (clone $periodeFin);
                break;
            case self::VUE_PERIODE_MOIS:
                $type = self::VUE_TYPE_CALENDRIER;
                $periodeDebut = (clone $dateDebut)->modify('first day of this month');
                $periodeFin = (clone $dateDebut)->modify('last day of this month');
                $periodeDebutRange = ($periodeDebut->format('N') == 1) ? (clone $periodeDebut) : (clone $periodeDebut)->modify('last monday');
                $periodeFinRange = ($periodeFin->format('N') == 7) ? (clone $periodeFin) : (clone $periodeFin)->modify('next sunday');
                break;
            case self::VUE_PERIODE_MOINS_90JOURS:
                $type = self::VUE_TYPE_TABLEAU;
                $periodeDebut = (clone $dateDebut)->sub(new \DateInterval('P90D'));
                $periodeFin = $dateDebut;
                $periodeDebutRange = $periodeDebut;
                $periodeFinRange = (clone $periodeFin);
                break;
            case self::VUE_PERIODE_DANS_120JOURS:
                $type = self::VUE_TYPE_TABLEAU;
                $periodeDebut = $dateDebut;
                $periodeFin = (clone $dateDebut)->add(new \DateInterval('P120D'));
                $periodeDebutRange = $periodeDebut;
                $periodeFinRange = (clone $periodeFin);
                break;
        }

        $periodeFin->setTime(23, 59, 59);

        $datePeriode = new \DatePeriod($periodeDebutRange, new \DateInterval('P1D'), $periodeFinRange);
        foreach ($datePeriode as $date) {
            $periodeRange[] = $date;
        }
        $periodeRange[] = $periodeFinRange;
    }

    /**
     * @Route(
     *     "/calendrier/global/{dateDebut?}/{vuePeriode?7jours}",
     *     name="calendrier-global",
     *     requirements={
     *          "dateDebut"="\d{4}-\d{2}-\d{2}",
     *          "vuePeriode"="7jours|jours|semaines|mois|-90jours|120jours",
     *     }
     * )
     */
    public function global(?string $dateDebut, ?string $vuePeriode, EntityManagerInterface $em, Request $request, OperationService $operationService) : Response
    {
        // On récupère la date de début de la période
        $dateDebut = new \DateTime($dateDebut);
        $dateDebut->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
        // On génère la navigation
        $navigation = $this->getPeriodeNavigationArray($dateDebut, $vuePeriode);

        // On définie notre période ainsi que le type de vue à afficher à l'utilisateur.
        $type = $periodeDebut = $periodeFin = null;
        $periodeRange = [];
        $this->getTypePeriodeAffichage($dateDebut, $vuePeriode, $type, $periodeDebut, $periodeFin, $periodeRange);

        // Récupération des autres éléments dont nous avons besoin dans la vue
        $pilotes = $em->getRepository(Pilote::class)->listePilotesFiltre()->getResult();

        // On récupère les données
        $operations = $operationService->findAllSurPeriode($periodeDebut, $periodeFin);

        // On traite les données pour pouvoir les afficher de manière efficace en front (et on en profite pour compter
        //  nos opérations !)
        $listeOperations = [];
        $statistiques = [ 'total' => 0, 'gesip' => 0, 'mepssi' => 0, 'mepssiStatut' => [] ];

        // On parcourt les opérations récupérés depuis la base de données pour la période donnée
        foreach ($operations as $ope) {
            // On enveloppe notre object avec l'entité factice Operation (pour faciliter l'utilisation future dans l'affichage)
            $operation = new Operation($ope);
            $statistiques['total']++;

            // Si nous sommes en présence d'une demande d'intervention
            if ($operation->getOriginalClass() === DemandeIntervention::class) {
                $statistiques['gesip']++;

            // Sinon si nous sommes en présence d'une mep ssi
            } elseif ($operation->getOriginalClass() === MepSsi::class) {
                $statistiques['mepssi']++;
                if (!isset($statistiques['mepssiStatut'][$operation->getMepStatutLabel()])) {
                    $statistiques['mepssiStatut'][$operation->getMepStatutLabel()] = 0;
                }
                $statistiques['mepssiStatut'][$operation->getMepStatutLabel()]++;
            }

            // Si c'est une demande d'intervention, alors on itère par impacts (car la restitution se fait par impact).
            if ($operation->getOriginalClass() === DemandeIntervention::class) {
                foreach ($operation->getOriginal()->getImpacts() as $impact) {
                    // On ajoute l'impact que l'on souhaite mettre en avant ici.
                    $operation = new Operation($ope, $impact);

                    // Si nous sommes en vue calendrier, nous devons trier par date
                    if ($type === self::VUE_TYPE_CALENDRIER) {
                        $debut = (clone $impact->getDateDebut())->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
                        $fin = (clone $impact->getDateFinMini())->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
                        $periode = new \DatePeriod($debut, new \DateInterval('P1D'), $fin);
                        foreach ($periode as $date) {
                            $listeOperations[$date->format('Y-m-d')][] = $operation;
                        }
                        $listeOperations[$fin->format('Y-m-d')][] = $operation;

                    // Dans les autres vues, pas besoin de trier par date !
                    } else {
                        $listeOperations[] = $operation;
                    }
                }

            // Sinon, alors la restitution ne pose pas de soucis et nous pouvons donc ajouter facilement l'operation directement.
            } elseif ($operation->getOriginalClass() === MepSsi::class) {
                // Si nous sommes en vue calendrier, nous devons trier par date
                if ($type === self::VUE_TYPE_CALENDRIER) {
                    $debut = $operation->getInterventionDebut()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
                    $fin = $operation->getInterventionFin()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
                    $periode = new \DatePeriod($debut, new \DateInterval('P1D'), $fin);
                    foreach ($periode as $date) {
                        $listeOperations[$date->format('Y-m-d')][] = $operation;
                    }
                    $listeOperations[$fin->format('Y-m-d')][] = $operation;

                // Dans les autres vues, pas besoin de trier par date !
                } else {
                    $listeOperations[] = $operation;
                }
            }
        }

        // On tri l'ordre de restitution en fonction des dates et heures
        if ($type === self::VUE_TYPE_CALENDRIER) {
            foreach ($listeOperations as $date => $value) {
                usort($listeOperations[$date], function ($a, $b) {
                    return ($a->getOperationType() === Operation::TYPE_MEPSSI || $a->getDateTri() < $b->getDateTri()) ? -1 : 1;
                });
            }
        } else {
            usort($listeOperations, function ($a, $b) {
                return ($a->getDateTri() < $b->getDateTri()) ? -1 : 1;
            });
        }

        // Si nous avons posté les informations, alors nous devons faire un export XLSX
        if ($request->isMethod('POST')) {
            // Construit le fichier xlsx
            $spreadsheet = new Spreadsheet();
            $dateDebutString = $periodeDebut->format('d/m/Y');
            $dateFinString = $periodeFin->format('d/m/Y');
            $fileExport = sprintf("export_%s-%s.xlsx", $periodeDebut->format('Ymd'), $periodeFin->format('Ymd'));
            $titreExport = sprintf("Du %s au %s", $dateDebutString, $dateFinString);

            if ($dateDebutString === $dateFinString) {
                $fileExport = sprintf("export_%s.xlsx", $periodeDebut->format('Ymd'));
                $titreExport = sprintf("Du %s", $dateDebutString);
            }

            $spreadsheet->getProperties()
                ->setCreator('Gesip')
                ->setTitle("Taux de disponibilité des composants")
                ->setSubject($titreExport);

            // Défini des styles utilisés dans le fichier excel
            $header1 = [
                'font' => [ 'bold'  => true, 'size'  => 13, 'color' => ['argb' => '0000CC']],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];
            $header2 = [
                'font' => [ 'bold'  => true, 'size'  => 12, 'color' => ['argb' => '0000CC']],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];
            $header3 = [
                'font' => [ 'bold'  => true, 'size'  => 11 ],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];
            $values = [
                'font' => [ 'bold'  => false, 'size'  => 11 ],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];

            // On déclare la première feuille dans le fichier
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->setTitle("Calendrier");

            // 1ère et 2ème lignes
            $activeSheet->getCell('A1')->setValue("Calendrier MEP SSI, GESIP")->getStyle()->applyFromArray($header1);
            $activeSheet->mergeCells('A1:I1');
            $activeSheet->getCell('A2')->setValue($titreExport)->getStyle()->applyFromArray($header2);
            $activeSheet->mergeCells('A2:I2');
            $activeSheet->getRowDimension(1)->setRowHeight(25);
            $activeSheet->getRowDimension(2)->setRowHeight(25);

            // 3ère ligne
            $activeSheet->getCell('A3')->setValue('Intervention / MEP')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('B3')->setValue('Composant')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('C3')->setValue('Impact / Description')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('D3')->setValue('Palier')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('E3')->setValue('Équipe')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('F3')->setValue('Pilote')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('G3')->setValue('ESI')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('H3')->setValue('Date demande')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('I3')->setValue('Date validation')->getStyle()->applyFromArray($header3);
            $activeSheet->getRowDimension(3)->setRowHeight(25);

            // On parcourt les données
            $idx = 4;
            foreach ($listeOperations as $operation) {
                $activeSheet->getCell("A$idx")->setValue($operation->donneesInterventionMep())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("B$idx")->setValue($operation->donneesComposant())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("C$idx")->setValue($operation->donneesImpactDescription())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("D$idx")->setValue($operation->donneesPalier())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("E$idx")->setValue($operation->donneesEquipe())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("F$idx")->setValue($operation->donneesPilote())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("G$idx")->setValue($operation->donneesEsi())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("H$idx")->setValue($operation->donneesDateDemande())->getStyle()->applyFromArray($values);
                $activeSheet->getCell("I$idx")->setValue($operation->donneesDateValidation())->getStyle()->applyFromArray($values);
                $activeSheet->getRowDimension($idx)->setRowHeight(-1);
                $idx++;
            }

            // On calcule automatiquement les dimensions des cellules en fonction du contenu
            for ($ascii = ord('A'); $ascii <= ord('I'); $ascii++) {
                if ($ascii == ord('B') || $ascii == ord('C')) {
                    $activeSheet->getColumnDimension(chr($ascii))->setWidth(50);
                } else {
                    $activeSheet->getColumnDimension(chr($ascii))->setAutoSize(true);
                }
            }

            // Ouvre un flux pour envoyer la réponse
            $writer = new Xlsx($spreadsheet);
            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });

            // Défini les headers qui vont bien
            $response->headers->set('Content-Type', 'application/vnd.ms-excel');
            $response->headers->set('Content-Disposition', "attachment;filename=\"{$fileExport}\"");
            $response->headers->set('Cache-Control', 'max-age=0');

            // Envoi la réponse
            return $response;
        }

        // Sinon, on affiche la vue normale
        return $this->render('calendrier/global/global.html.twig', [
            'pilotes' => $pilotes,
            'dateDebut' => $dateDebut,
            'vuePeriode' => $vuePeriode,
            'navigation' => $navigation,
            'typeAffichage' => $type,
            'periodeRange' => $periodeRange,
            'periodeDebut' => $periodeDebut,
            'periodeFin' => $periodeFin,
            'operations' => $listeOperations,
            'statistiques' => $statistiques
        ]);
    }
}
