<?php

namespace App\Controller\Meteo\Statistiques;

use App\Entity\Composant;
use App\Form\Meteo\Statistiques\TauxIndisponibilitesType;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class TauxIndisponibiliteController extends AbstractController
{
    /**
     * @Route("/meteo/statistiques/taux-indisponibilites", name="pourcentage-taux-indisponibilites")
     */
    public function index(Request $request): Response
    {
        $form = $this->createForm(TauxIndisponibilitesType::class);

        return $this->render('meteo/statistiques/taux-indisponibilite.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route(
     *  "/meteo/statistiques/taux-indisponibilites/{source}/{frequence}/{anneeDebut}/{anneeFin}/xlsx",
     *  name="pourcentage-taux-indisponibilites-export-xlsx",
     *  requirements={"source"="interventions|evenements", "frequence"="P1M|P3M|P6M|P1Y", "anneeDebut"="\d+", "anneeFin"="\d+"}
     * )
     */
    public function exportXlsx(string $source, string $frequence, int $anneeDebut, int $anneeFin, Request $request): Response
    {
        // Récupération des données
        $filtres = [];
        foreach (['equipe', 'pilote', 'exploitant'] as $parameter) {
            $filtres[$parameter] = $request->query->get($parameter);
        }

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
        $styleValueCell = [
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER ]
        ];

        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("Pourcentage d'indisponibilité des composants de {$anneeDebut} à {$anneeFin}")
            ->setSubject("Pourcentage d'indisponibilité des composants de {$anneeDebut} à {$anneeFin}")
            ->setDescription('Pourcentage d\'indisponibilité par composants.');

        // Récupère l'onglet courant et lui donne un titre
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setTitle("De début {$anneeDebut} à fin {$anneeFin}");

        // 1ère et 2ème lignes
        $activeWorksheet->getCell('A1')->setValue("Pourcentage d'indisponibilité des composants")->getStyle()->applyFromArray($header1);
        $activeWorksheet->mergeCells('A1:D1');
        $activeWorksheet->getCell('A2')->setValue("De début {$anneeDebut} à fin {$anneeFin}")->getStyle()->applyFromArray($header2);
        $activeWorksheet->mergeCells('A2:D2');
        $activeWorksheet->getRowDimension(1)->setRowHeight(25);
        $activeWorksheet->getRowDimension(2)->setRowHeight(25);
        $activeWorksheet->getRowDimension(3)->setRowHeight(30);

        // Récupère la liste des composants correspondants
        $composantRepository = $this->getDoctrine()->getManager()->getRepository(Composant::class);
        $composants = $composantRepository->getComposantIndisponibilites($filtres);
        $decallageLigne = 4;
        foreach ($composants as $composant) {
            $activeWorksheet->getCell('A' . $decallageLigne)
                ->setValue($composant->getLabel());
            $decallageLigne++;
        }

        // Récupère les valeurs des indisponibilités
        $periodeDebut = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anneeDebut . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));
        $periodeFin = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anneeFin + 1 . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));

        // Pour chaque période on récupère le taux de disponibilité
        $subPeriodeDebut = clone($periodeDebut);
        $column = '2';
        while ($subPeriodeDebut->getTimestamp() < $periodeFin->getTimestamp()) {
            // On calcule la fin de la période courante
            $subPeriodeFin = $subPeriodeDebut->add(new \DateInterval($frequence))->sub(new \DateInterval('PT1S'));
            // On récupère les taux d'indisponibilités pour la période courante
            $disponibilitesParComposant = $composantRepository->getTauxIndisponibilites(
                $source,
                \DateTime::createFromImmutable($subPeriodeDebut),
                \DateTime::createFromImmutable($subPeriodeFin),
                $composants
            );
            // On parcourt tous les composants pour écrire les cellules de la période correspondante
            $decallageLigne = 3;
            $activeWorksheet->getCellByColumnAndRow($column, $decallageLigne)
                ->setValue(str_replace(' au ', "\nau ", $disponibilitesParComposant['periode']['label']))
                ->getStyle()->applyFromArray($header3);
            $decallageLigne++;
            foreach ($composants as $composant) {
                $value = '';
                if ($disponibilitesParComposant['indisponibilite'][$composant->getId()] !== '') {
                    $value = $disponibilitesParComposant['indisponibilite'][$composant->getId()] . ' %';
                }
                $activeWorksheet->getCellByColumnAndRow($column, $decallageLigne)->setValue($value)
                    ->getStyle()->applyFromArray($styleValueCell);
                $decallageLigne++;
            }
            // Increment, pour passer à la période suivante
            $subPeriodeDebut = $subPeriodeDebut->add(new \DateInterval($frequence));
            $column++;
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
        $response->headers->set('Content-Disposition', "attachment;filename=\"taux-indisponibilites-export_{$anneeDebut}-{$anneeFin}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }

    /**
     * @Route(
     *  "/meteo/statistiques/taux-indisponibilites/{source}/{frequence}/{anneeDebut}/{anneeFin}/pdf",
     *  name="pourcentage-taux-indisponibilites-export-pdf",
     *  requirements={"source"="interventions|evenements", "frequence"="P1M|P3M|P6M|P1Y", "anneeDebut"="\d+", "anneeFin"="\d+"}
     * )
     */
    public function exportPdf(string $source, string $frequence, int $anneeDebut, int $anneeFin, Request $request, Pdf $pdf): PdfResponse
    {
        // Récupération des données
        $filtres = [];
        foreach (['equipe', 'pilote', 'exploitant'] as $parameter) {
            $filtres[$parameter] = $request->query->get($parameter);
        }

        // Récupère la liste des composants correspondants
        $composantRepository = $this->getDoctrine()->getManager()->getRepository(Composant::class);
        $composants = $composantRepository->getComposantIndisponibilites($filtres);

        // Récupère les valeurs des indisponibilités
        $periodeDebut = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anneeDebut . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));
        $periodeFin = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anneeFin + 1 . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));

        // Pour chaque période on récupère le taux de disponibilité (en répartissant dans des pages de 12)
        $periodePages = [];
        $pageSize = 12;
        $subPeriodeDebut = clone($periodeDebut);
        while ($subPeriodeDebut->getTimestamp() < $periodeFin->getTimestamp()) {
            $pagePeriodesCount = 0;
            $periodePage = [
                'debut' => $subPeriodeDebut
            ];
            while ($subPeriodeDebut->getTimestamp() < $periodeFin->getTimestamp() && $pagePeriodesCount < $pageSize) {
                // On calcule la fin de la période courante
                $subPeriodeFin = $subPeriodeDebut->add(new \DateInterval($frequence))->sub(new \DateInterval('PT1S'));
                // On récupère les taux d'indisponibilités pour la période courante
                $periodePage['periodes'][] = $composantRepository->getTauxIndisponibilites(
                    $source,
                    \DateTime::createFromImmutable($subPeriodeDebut),
                    \DateTime::createFromImmutable($subPeriodeFin),
                    $composants
                );
                $periodePage['fin'] = $subPeriodeFin;
                $pagePeriodesCount++;
                // Increment, pour passer à la période suivante
                $subPeriodeDebut = $subPeriodeDebut->add(new \DateInterval($frequence));
            }
            $periodePages[] = $periodePage;
        }

        // On génère une vue avec les résultats
        $html = $this->renderView('meteo/statistiques/taux-indisponibilite.pdf.html.twig', [
            'baseAssets'    => $this->getParameter('kernel.project_dir') . '/public',
            'periode' => [
                'debut' => $anneeDebut,
                'fin'   => $anneeFin
            ],
            'composants'     => $composants,
            'periodePages'   => $periodePages,
        ]);

        // On génère et renvoie un binaire Pdf au format paysage à partir du code html généré précédement
        return new PdfResponse(
            $pdf->getOutputFromHtml($html, ['orientation' => 'Landscape']),
            "taux-indisponibilites-export_{$anneeDebut}-{$anneeFin}.pdf"
        );
    }
}
