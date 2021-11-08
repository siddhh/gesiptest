<?php

namespace App\Controller\Meteo\Statistiques;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\Meteo\Statistiques\EtatInterventionsType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;

class EtatInterventionsController extends AbstractController
{
    /**
     * @Route("/meteo/statistiques/etat-interventions", name="meteo-statistiques-etat_interventions")
     */
    public function etatGlobalInterventions(Request $request): Response
    {
        $form = $this->createForm(EtatInterventionsType::class);

        return $this->render('meteo/statistiques/etat-interventions.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route(
     *  "/meteo/statistiques/etat-interventions/{anneeDebut}/{anneeFin}/xlsx",
     *  name="meteo-statistiques-etat_interventions-export-xlsx",
     *  requirements={"anneeDebut"="\d+", "anneeFin"="\d+"}
     * )
     */
    public function exportXlsx(int $anneeDebut, int $anneeFin, Request $request): Response
    {
        // Récupération des données
        $donnees = json_decode($request->query->get('donnees'));

        // Définit des styles utilisés dans le fichier excel
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
        $styleValueCell = [
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER ]
        ];

        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("État global des interventions de {$anneeDebut} à {$anneeFin}")
            ->setSubject("État global des interventions de {$anneeDebut} à {$anneeFin}")
            ->setDescription('État global des interventions.');

        // Récupère l'onglet courant et lui donne un titre
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setTitle("De début {$anneeDebut} à fin {$anneeFin}");

        // 1ère et 2ème lignes
        $activeWorksheet->getCell('A1')->setValue("État global des interventions")->getStyle()->applyFromArray($header1);
        $activeWorksheet->mergeCells('A1:D1');
        $activeWorksheet->getCell('A2')->setValue("De début {$anneeDebut} à fin {$anneeFin}")->getStyle()->applyFromArray($header2);
        $activeWorksheet->mergeCells('A2:D2');
        $activeWorksheet->getRowDimension(1)->setRowHeight(25);
        $activeWorksheet->getRowDimension(2)->setRowHeight(25);
        $activeWorksheet->getRowDimension(3)->setRowHeight(30);

        // Charge les données dans le tableau
        $activeWorksheet->fromArray($donnees, null, 'A4');
        $maxcolIndex = Coordinate::columnIndexFromString($activeWorksheet->getHighestDataColumn());
        $maxrowIndex = $activeWorksheet->getHighestDataRow();
        $activeWorksheet->getStyle('A4:A' . $maxrowIndex)->applyFromArray($header3);
        for ($i = 2; $i <= $maxcolIndex; $i++) {
            $activeWorksheet->getCellByColumnAndRow($i, 4)->getStyle()->applyFromArray($header3);
            for ($j = 5; $j <= $maxrowIndex; $j++) {
                $activeWorksheet->getCellByColumnAndRow($i, $j)->getStyle()->applyFromArray($styleValueCell);
            }
        }

        // Redimensionne toutes les cellules contenant des données automatiquement
        $maxcolIndex = Coordinate::columnIndexFromString($activeWorksheet->getHighestDataColumn());
        for ($col = 1; $col <= $maxcolIndex; $col++) {
            $activeWorksheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        // Ouvre un flux pour envoyer la réponse
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        // Défini les headers qui vont bien
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"etat-interventions-export_{$anneeDebut}-{$anneeFin}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }

    /**
     * @Route(
     *  "/meteo/statistiques/etat-interventions/{anneeDebut}/{anneeFin}/pdf",
     *  name="meteo-statistiques-etat_interventions-export-pdf",
     *  requirements={"anneeDebut"="\d+", "anneeFin"="\d+"}
     * )
     */
    public function exportPdf(int $anneeDebut, int $anneeFin, Request $request, Pdf $pdf): PdfResponse
    {
        // Récupération des données
        $donnees = json_decode($request->query->get('donnees'));
        $entetes = array_shift($donnees);

        // On génère une vue avec les résultats
        $html = $this->renderView('meteo/statistiques/etat-interventions.pdf.html.twig', [
            'baseAssets'    => $this->getParameter('kernel.project_dir') . '/public',
            'periode' => [
                'debut' => $anneeDebut,
                'fin'   => $anneeFin
            ],
            'entetes'     => $entetes,
            'lignes'     => $donnees,
        ]);

        // On génère et renvoie un binaire Pdf au format paysage à partir du code html généré précédement
        return new PdfResponse(
            $pdf->getOutputFromHtml($html, ['orientation' => 'Landscape']),
            "etat-interventions-export_{$anneeDebut}-{$anneeFin}.pdf"
        );
    }
}
