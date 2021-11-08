<?php

namespace App\Controller\Meteo\Statistiques;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use App\Entity\Service;
use App\Entity\Composant;
use App\Entity\Meteo\Evenement;
use App\Entity\References\MotifIntervention;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Form\Meteo\Statistiques\RepartitionType;
use App\Utils\ChaineDeCaracteres;

class RepartitionController extends AbstractController
{
    const EXPORT_XLSX = 'xlsx';
    const EXPORT_PDF = 'pdf';

    /** @var Security $security */
    private $security;

    /**
     * Constructeur
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Prépare le tableau pour l'affichage des répartions
     * @param array $data
     * @return array
     */
    private function calculTableauRepartition(array $data) : array
    {
        $tableau = [];
        $dataDebutString = $data['annee'] . '/' . $data['mois'] . '/' . '01 00:00:00';
        $dataFinString = (($data['mois'] == 12) ? $data['annee'] + 1 : $data['annee'])
           . '/' . (($data['mois'] + 1) % 12) . '/' . '01 00:00:00';
        $dateDebutPeriode = \DateTime::createFromFormat('Y/m/d H:i:s', $dataDebutString, new \DateTimeZone('Europe/Paris'));
        $dateFinPeriode = \DateTime::createFromFormat('Y/m/d H:i:s', $dataFinString, new \DateTimeZone('Europe/Paris'));
        $dateFinPeriode->sub(new \DateInterval('PT1S'));
        $exploitant = !empty($data['exploitant']) ? $data['exploitant'] : null;

        $listeComposants = $this->getDoctrine()->getRepository(Composant::class)
            ->getComposantsMeteoExploitant($exploitant);

        $evenements = $this->getDoctrine()->getRepository(Evenement::class)
            ->listeEvenementsFiltre($listeComposants, $dateDebutPeriode, $dateFinPeriode);

        $tableauComposant = [
           'label' => '',
           'incidents' => [
               'nombre' => 0,
               'duree' => 0,
               'dureeHumaine' => 0,
           ],
           'intervTech' => [
               'nombre' => 0,
               'duree' => 0,
               'dureeHumaine' => 0,
           ],
           'intervAppli' => [
               'nombre' => 0,
               'duree' => 0,
               'dureeHumaine' => 0,
           ],
           'total' => [
               'nombre' => 0,
               'duree' => 0,
               'dureeHumaine' => 0,
           ],
        ];
        foreach ($listeComposants as $composant) {
            $composantId = $composant->getId();
            $tableau[$composantId] = array_merge([], $tableauComposant);
            $tableau[$composantId]['label'] = $composant->getLabel();
        }
        $tableau['total'] = array_merge([], $tableauComposant);
        $tableau['total']['label'] = 'Totaux par évènement';
        unset($tableau['total']['total']);
        // Récupération des ids de motifs d'interventions par nature
        $motifInterventionParNatureIds = $this->getMotifInterventionParNatureIds();
        foreach ($evenements as $evenement) {
            $composantId = $evenement->getComposant()->getId();
            $typeOperationId = $evenement->gettypeOperation()->getId();
            $natureId = 'incidents';    // Nature par défaut
            foreach ($motifInterventionParNatureIds as $nature => $motifInventionIds) {
                if (in_array($typeOperationId, $motifInventionIds)) {
                    $natureId = $nature;
                    break;
                }
            }
            if (isset($tableau[$composantId])) {
                $tableau[$composantId][$natureId]['nombre']++;
                $duree = date_diff($evenement->getDebut(), $evenement->getFin());
                $dureeMinutes = $duree->format('%d') * 360 + $duree->format('%h') * 60 + $duree->format('%i');
                $tableau[$composantId][$natureId]['duree'] += $dureeMinutes;
                $tableau[$composantId][$natureId]['dureeHumaine'] = ChaineDeCaracteres::minutesEnLectureHumaineSimple($tableau[$composantId][$natureId]['duree']);

                //Total par composant
                $tableau[$composantId]['total']['nombre']++;
                $tableau[$composantId]['total']['duree'] += $dureeMinutes;
                $tableau[$composantId]['total']['dureeHumaine'] = ChaineDeCaracteres::minutesEnLectureHumaineSimple($tableau[$composantId]['total']['duree']);

                //Totaux pour tous les composants
                $tableau['total'][$natureId]['nombre']++;
                $tableau['total'][$natureId]['duree'] += $dureeMinutes;
                $tableau['total'][$natureId]['dureeHumaine'] = ChaineDeCaracteres::minutesEnLectureHumaineSimple($tableau['total'][$natureId]['duree']);
            }
        }

        // ajout des parametres de filtrage
        $tableau['filtres'] = [
           'exploitant' => $exploitant,
           'debut' => $dateDebutPeriode,
           'fin' => $dateFinPeriode
        ];

        return $tableau;
    }

    /**
     * Retourne la liste des ids de motifs d'interventions répartis par catégorie
     */
    private function getMotifInterventionParNatureIds() : array
    {
        // Récupération des catégories
        $interventionApplicativeMotifInterventions = $this->getDoctrine()->getRepository(MotifIntervention::class)->findAll();
        $intervTechMotifInterventionLabels = [
            'Opération d\'exploitation',
            'Maintenance technique',
            'Opération de travaux sur site',
            'Ouverture de droits',
            'Ouverture de flux',
            'Résolution d\'incident'
        ];
        $intervAppliMotifInterventionLabels = [
            'Maintenance applicative'
        ];
        $motifsInterventionIds = [
            'intervTech'    => [],
            'intervAppli'   => [],
            'incidents'     => [],
        ];
        foreach ($interventionApplicativeMotifInterventions as $motifIntervention) {
            $motifInterventionLabel = $motifIntervention->getLabel();
            if (in_array($motifInterventionLabel, $intervTechMotifInterventionLabels)) {
                $motifsInterventionIds['intervTech'][] = $motifIntervention->getId();
            } elseif (in_array($motifInterventionLabel, $intervAppliMotifInterventionLabels)) {
                $motifsInterventionIds['intervAppli'][] = $motifIntervention->getId();
            } else {
                $motifsInterventionIds['incidents'][] = $motifIntervention->getId();
            }
        }
        return $motifsInterventionIds;
    }

     /**
     * @Route(
     *  "/meteo/statistiques/repartition/{exploitant?}/{mois?}/{annee?}/{exportType?}",
     *  name="meteo-statistiques-repartition",
     *  requirements={
     *      "exploitant"="\d+",
     *      "mois"="\d+",
     *      "annee"="\d+",
     *      "exportType"="pdf|xlsx"
     *  }
     * )
     */
    public function index(?Service $exploitant, ?int $mois, ?int $annee, ?string $exportType, Pdf $pdf): Response
    {
        $tableau = [];
        $form = $this->createForm(RepartitionType::class);
        $form->submit([
            'exploitant'    => !empty($exploitant) ? $exploitant->getId() : null,
            'mois'          => !empty($mois) ? $mois : intval(date('m')),
            'annee'         => !empty($annee) ? $annee : intval(date('Y')),
        ]);
        if ($form->isValid()) {
            $data = $form->getData();
            // Si le service n'a pas le rôle gestion, le champ exploitant n'est pas transmit lors de la soumission, on force sa valeur ici
            if (!$this->isGranted(Service::ROLE_GESTION)) {
                $data['exploitant'] = $this->getUser();
            }
            $tableau = $this->calculTableauRepartition($data);
        }

        if ($exportType === self::EXPORT_PDF) {
            // On génère le code HTML qui permettra de créer le PDF.
            $html = $this->renderView('meteo/statistiques/repartition.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
                'tableau' => $tableau,
            ]);
            // On génère et renvoie un binaire Pdf au format paysage à partir du code html généré précédement
            $debutString = $tableau['filtres']['debut']->format('my');
            return new PdfResponse(
                $pdf->getOutputFromHtml($html, ['orientation' => 'Landscape']),
                "repartition-indisponibilite_{$debutString}.pdf"
            );
        } elseif ($exportType === self::EXPORT_XLSX) {
            return $this->generateXlsx($tableau);
        } else {
            return $this->render('meteo/statistiques/repartition.html.twig', [
                'formFiltres'   => $form->createView(),
                'tableau'       => $tableau
            ]);
        }
    }

    /**
     * Génère une réponse sous forme d'un fichier xlsx
     */
    private function generateXlsx(array $tableau): Response
    {
        // Défini des styles utilisés dans le fichier excel
        $header1 = [
            'font' => [ 'bold'  => true, 'size'  => 13, 'color' => ['argb' => '0000CC']],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $header2 = [
            'font' => [ 'bold'  => true, 'size'  => 12, 'color' => ['argb' => '000000']],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $header3 = [
            'font' => [ 'bold'  => true, 'size'  => 11 ],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $styleValueCell = [
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER ]
        ];
        $debutString = $tableau['filtres']['debut']->format('my');
        $exploitantLabel = !empty($tableau['filtres']['exploitant']) ? $tableau['filtres']['exploitant']->getLabel() : null;

        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("Répartition des incidents et des indisponibilités {$debutString}")
            ->setSubject("Répartition des incidents et des indisponibilités {$debutString}")
            ->setDescription('Répartition des incidents et des indisponibilités.');

        // Récupère l'onglet courant et lui donne un titre
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setTitle("Répartition");

        // 1ère et 2ème lignes
        $activeWorksheet->getCell('A1')->setValue("Répartition des incidents et des indisponibilités")->getStyle()->applyFromArray($header1);
        $activeWorksheet->mergeCells('A1:I1');
        $activeWorksheet->getCell('A2')->setValue("Composant ")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('B2')->setValue("Incidents-nombre")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('C2')->setValue("Incidents-durée")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('D2')->setValue("Interventions \n techniques-nombre")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('E2')->setValue("Interventions \n techniques-durée")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('F2')->setValue("Interventions \n applicatives-nombre")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('G2')->setValue("Interventions \napplicatives-durée")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('H2')->setValue("Nombre total")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('I2')->setValue("Durée total")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getRowDimension(1)->setRowHeight(25);
        $activeWorksheet->getRowDimension(2)->setRowHeight(30);
        $decallageLigne = 3;
        foreach ($tableau as $composantId => $composantData) {
            if ($composantId !== 'filtres') {
                if ($composantId !== 'total') {
                    $activeWorksheet->getCell('A' . $decallageLigne)->setValue($composantData['label']);
                } else {
                    $activeWorksheet->getCell('A' . $decallageLigne)->setValue($composantData['label']);
                }
                $rowNumber = ord('B');
                foreach (['incidents', 'intervTech', 'intervAppli'] as $nature) {
                    if ($composantId === 'total') {
                        $activeWorksheet->getCell(chr($rowNumber) . $decallageLigne)->setValue($tableau[$composantId][$nature]['nombre']);
                        $activeWorksheet->getCell(chr($rowNumber + 1) . $decallageLigne)->setValue($tableau[$composantId][$nature]['dureeHumaine']);
                    } else {
                        $activeWorksheet->getCell(chr($rowNumber) . $decallageLigne)->setValue($tableau[$composantId][$nature]['nombre']);
                        $activeWorksheet->getCell(chr($rowNumber + 1) . $decallageLigne)->setValue($tableau[$composantId][$nature]['dureeHumaine']);
                    }
                    $rowNumber += 2;
                }
                if (!empty($tableau[$composantId]['total'])) {
                    $activeWorksheet->getCell('H' . $decallageLigne)->setValue($tableau[$composantId]['total']['nombre']);
                    $activeWorksheet->getCell('I' . $decallageLigne)->setValue($tableau[$composantId]['total']['dureeHumaine']);
                }
                $decallageLigne++;
            }
        }

        $maxi = $activeWorksheet->getHighestRowAndColumn();
        $activeWorksheet->getStyle("A3:{$maxi['column']}{$maxi['row']}")->applyFromArray($styleValueCell);

        // Redimensionne toutes les cellules contenant des données automatiquement
        $maxcolIndex = Coordinate::columnIndexFromString($activeWorksheet->getHighestDataColumn());
        for ($col = 1; $col <= $maxcolIndex; $col++) {
            $activeWorksheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        //Ouvre un flux pour envoyer la réponse
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        // Défini les headers qui vont bien
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"repartition-export-{$debutString}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }
}
