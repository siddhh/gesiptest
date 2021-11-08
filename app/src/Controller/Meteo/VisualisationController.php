<?php

namespace App\Controller\Meteo;

use App\Entity\Composant;
use App\Entity\Service;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class VisualisationController extends AbstractController
{

    /**
     * @Route(path="/meteo/consultation/pdf/{periode}/{serviceExploitant?}", name="meteo-visualisation-export-pdf")
     */
    public function exportPdf(string $periode, Service $serviceExploitant = null, Request $request, Pdf $pdf): Response
    {
        // On récupère notre période de temps par rapport au début passé en paramètre
        $periodeDebut = \DateTime::createFromFormat('Ymdhis', $periode . '000000', new \DateTimeZone('Europe/Paris'));
        $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTime(23, 59, 59);

        // On récupère nos évènements par services et par composants (on tri les dates des évènements par la suite)
        $services = $this->getDoctrine()->getRepository(Service::class)
            ->listeServicesExploitantAvecComposantsEtEvenements($serviceExploitant, $request->get('c'));

        // On récupère les ids de tous les composants qui seront affichés pour récupérer leur météo
        $idsComposants = [];
        /** @var Service $service */
        foreach ($services as $service) {
            $idsComposants = array_merge($idsComposants, array_column($service->getComposantsExploitant()->getValues(), 'id'));
        }
        $idsComposants = array_unique($idsComposants);

        // On récupère la météo des composants en fonction de leurs ids et de la période
        $meteoComposants = $this->getDoctrine()->getRepository(Composant::class)->indicesMeteoComposants($idsComposants, $periodeDebut);

        // On calcule les chaine pour l'affichage de la période
        $periodeDebutStr = $periodeDebut->format('d');
        $periodeFinStr = $periodeFin->format('d/m/Y');
        if ($periodeDebut->format('Y') !== $periodeFin->format('Y')) {
            $periodeDebutStr = $periodeDebut->format('d/m/Y');
        } elseif ($periodeDebut->format('m') !== $periodeFin->format('m')) {
            $periodeDebutStr = $periodeDebut->format('d/m');
        }

        // On génère une vue avec les résultats
        $html = $this->renderView('meteo/pdf.html.twig', [
            'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
            'periode' => [
                'debut' => $periodeDebutStr,
                'debutobj' => $periodeDebut,
                'fin' => $periodeFinStr,
                'finobj' => $periodeFin
            ],
            'services' => $services,
            'meteoComposants' => $meteoComposants,
        ]);

        // On en fait un pdf que l'on transmet renvoie
        return new PdfResponse(
            $pdf->getOutputFromHtml($html),
            'export.pdf'
        );
    }

    /**
     * @Route(path="/meteo/consultation/xlsx/{periode}/{serviceExploitant?}", name="meteo-visualisation-export-xlsx")
     */
    public function exportXlsx(string $periode, Service $serviceExploitant = null, Request $request): Response
    {
        // On récupère notre période de temps par rapport au début passé en paramètre
        $periodeDebut = \DateTime::createFromFormat('Ymdhis', $periode . '000000', new \DateTimeZone('Europe/Paris'));
        $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTime(23, 59, 59);

        // On récupère nos évènements par services et par composants (on tri les dates des évènements par la suite)
        $services = $this->getDoctrine()->getRepository(Service::class)
            ->listeServicesExploitantAvecComposantsEtEvenements($serviceExploitant, $request->get('c'));

        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $dateDebutString = $periodeDebut->format('d/m/Y');
        $dateFinString = $periodeFin->format('d/m/Y');
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("Export météo du {$dateDebutString} au {$dateFinString}")
            ->setSubject("Export météo du {$dateDebutString} au {$dateFinString}")
            ->setDescription("Export météo: résumé des indisponibilités de composants par exploitant");

        // Défini des styles utilisés dans le fichier excel
        $composantTitleStyles = [
            'font' => [
                'bold'  => true,
                'size'  => 18,
                'color' => ['argb' => '0000CC']
            ]
        ];

        // On récupère la liste des composants
        $listeComposantsId = [];
        foreach ($services as $index => $exploitant) {
            foreach ($exploitant->getComposantsExploitant() as $composant) {
                $listeComposantsId[] = $composant->getId();
            }
        }
        $listeComposantsId = array_unique($listeComposantsId);

        // On récupère les indices météo des composants pour la période souhaitée
        $meteoParComposants = $this->getDoctrine()->getRepository(Composant::class)
            ->indicesMeteoComposants($listeComposantsId, $periodeDebut);

        // On récupère la liste des services exploitants et leurs composants associés
        $composantPointers = [];
        foreach ($services as $index => $exploitant) {
            // Création d'un nouvel onglet par service exploitant
            $activeWorksheet = null;
            $exploitantLabel = mb_substr($exploitant->getLabel(), 0, 31);
            if ($index > 0) {
                $activeWorksheet = new Worksheet($spreadsheet, $exploitantLabel);
                $spreadsheet->addSheet($activeWorksheet);
            } else {
                $activeWorksheet = $spreadsheet->getActiveSheet()->setTitle($exploitantLabel);
            }
            // Pour chaque composant trouvé
            $decallageLigne = 2;
            $exploitantComposantDejaExporteIds = [];
            foreach ($exploitant->getComposantsExploitant() as $composant) {
                $composantId = $composant->getId();
                if (!in_array($composantId, $exploitantComposantDejaExporteIds)) {
                    $exploitantComposantDejaExporteIds[] = $composantId;
                    // Ajoute le titre du composant
                    $activeWorksheet->getCell('A' . $decallageLigne)
                        ->setValue($composant->getLabel())
                        ->getStyle()->applyFromArray($composantTitleStyles);
                    $activeWorksheet->getRowDimension($decallageLigne)->setRowHeight(25);
                    // Ajoute une référence pointant juste avant le titre pour ajouter la météo après
                    $composantPointers[] = [
                        'id'        => $composant->getId(),
                        'worksheet' => $activeWorksheet,
                        'ligne'     => $decallageLigne + 1
                    ];
                    $decallageLigne += 4;

                    // Si la météo n'est pas N.C
                    $evenementsBuffer = [];
                    if (!isset($meteoParComposants[$composant->getId()]) || $meteoParComposants[$composant->getId()]['indice'] !== \App\Entity\Meteo\Composant::NC) {
                        // On utilise un buffer pour injecter la totalité des évenements d'un composant en une seule fois
                        $evenementsBuffer = [
                            ['Période', 'Impact', 'Type d\'opération', 'Description', 'Commentaire'],
                        ];
                        foreach ($composant->getEvenementsMeteoParPeriode($periodeDebut, $periodeFin) as $evenement) {
                            $evenementsBuffer[] = [
                                $evenement->getDebut()->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('d-m-Y H:i') . ' - ' . $evenement->getFin()->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('d-m-Y H:i'),
                                $evenement->getImpact()->getLabel(),
                                $evenement->getTypeOperation()->getLabel(),
                                $evenement->getDescription(),
                                $evenement->getCommentaire(),
                            ];
                        }
                        $activeWorksheet->fromArray($evenementsBuffer, null, 'A' . $decallageLigne);
                    }

                    // On fait un peu d'espace pour le prochain composant..
                    $decallageLigne += count($evenementsBuffer) + 3;
                }
            }
        }

        // On ajoute les indices météos par composants
        foreach ($meteoParComposants as $meteoComposant) {
            // Retrouve les endroits référencés dans le fichier concernant ce composant
            $composantId = $meteoComposant['id'];
            $composantPointerSelecteds = array_filter($composantPointers, function ($composantPointer) use ($composantId) {
                return $composantId === $composantPointer['id'];
            });
            foreach ($composantPointerSelecteds as $composantPointer) {
                // Affecte la météo
                $worksheet = $composantPointer['worksheet'];
                $decallageLigne = $composantPointer['ligne'];
                $this->addImageMeteo($worksheet, $meteoComposant['indice'], 'A' . $decallageLigne);
                $worksheet->getRowDimension($decallageLigne)->setRowHeight(50);
                $decallageLigne ++;
                if ($meteoComposant['disponibilite']) {
                    $worksheet->getCell('A' . $decallageLigne)->setValue('Taux de disponibilité sur la période : ');
                    $worksheet->getCell('B' . $decallageLigne)->setValue($meteoComposant['disponibilite'] . '%');
                }
            }
        }

        // Redimensionne toutes les cellules automatiquement
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            for ($ascii = ord('A'); $ascii <= ord('E'); $ascii++) {
                $worksheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            }
        }

        // On remet le premier onglet actif
        $spreadsheet->setActiveSheetIndex(0);

        // Ouvre un flux pour envoyer la réponse
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        // Défini les headers qui vont bien
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"meteo-export_{$dateDebutString}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }

    /**
     * Ajoute le fichier image météo au document à l'endroit souhaité
     * @param string $imageMeteo
     * @param int $imageHeight
     * @return void
     */
    private function addImageMeteo(Worksheet $worksheet, string $imageMeteo, string $position, int $imageHeight = 64): void
    {
        // récupère le chemin du fichier image
        $imagePath = $this->getParameter('kernel.project_dir') . "/public/assets/img/meteo-{$imageMeteo}.png";
        if (is_file($imagePath) && is_readable($imagePath)) {
            $drawing = new Drawing();
            $drawing->setName($imageMeteo);
            $drawing->setName('Météo ' . $imageMeteo);
            $drawing->setPath($imagePath);
            $drawing->setHeight($imageHeight);
            $drawing->setCoordinates($position);
            $drawing->setWorksheet($worksheet);
        } else {
            throw new \Exception("Image météo {$imageMeteo} introuvable ou illisible.");
        }
    }

    /**
     * @Route(path="/meteo/consultation/{periode}/{serviceExploitant?}", name="meteo-visualisation")
     */
    public function consultation(string $periode, Service $serviceExploitant = null, Request $request): Response
    {
        // On récupère notre période de temps par rapport au début passé en paramètre
        $periodeDebut = \DateTime::createFromFormat('Ymdhis', $periode . '000000', new \DateTimeZone('Europe/Paris'));
        $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTime(23, 59, 59);

        // On récupère nos évènements par services et par composants (on tri les dates des évènements par la suite)
        $services = $this->getDoctrine()->getRepository(Service::class)
            ->listeServicesExploitantAvecComposantsEtEvenements($serviceExploitant, $request->get('c'));

        // On récupère les ids de tous les composants qui seront affichés pour récupérer leur météo
        $idsComposants = [];
        /** @var Service $service */
        foreach ($services as $service) {
            $idsComposants = array_merge($idsComposants, array_column($service->getComposantsExploitant()->getValues(), 'id'));
        }
        $idsComposants = array_unique($idsComposants);

        // On récupère la météo des composants en fonction de leurs ids et de la période
        $meteoComposants = $this->getDoctrine()->getRepository(Composant::class)->indicesMeteoComposants($idsComposants, $periodeDebut);

        // On calcule les chaine pour l'affichage de la période
        $periodeDebutStr = $periodeDebut->format('d');
        $periodeFinStr = $periodeFin->format('d/m/Y');
        if ($periodeDebut->format('Y') !== $periodeFin->format('Y')) {
            $periodeDebutStr = $periodeDebut->format('d/m/Y');
        } elseif ($periodeDebut->format('m') !== $periodeFin->format('m')) {
            $periodeDebutStr = $periodeDebut->format('d/m');
        }

        // On génère une vue avec les résultats
        return $this->render('meteo/consultation/vue-meteo.html.twig', [
            'dateDebutString'   => $periode,
            'exploitantId'      => !empty($serviceExploitant) ? $serviceExploitant->getId(): null,
            'periode' => [
                'debut' => $periodeDebutStr,
                'debutobj' => $periodeDebut,
                'fin' => $periodeFinStr,
                'finobj' => $periodeFin
            ],
            'listeServices'  =>  $services,
            'meteoComposants' => $meteoComposants,
        ]);
    }
}
