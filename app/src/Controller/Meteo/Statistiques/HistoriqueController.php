<?php

namespace App\Controller\Meteo\Statistiques;

use App\Entity\Composant;
use App\Entity\Service;
use App\Form\Meteo\Statistiques\HistoriqueType;
use App\Utils\ChaineDeCaracteres;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class HistoriqueController extends AbstractController
{
    /**
     * On défini quelques constantes pour les différents types d'export
     */
    const EXPORT_XLSX = 'xlsx';
    const EXPORT_PDF = 'pdf';

    /** @var Pdf */
    private $pdfService;

    /**
     * HistoriqueController constructor.
     * (On va chercher les dépendances nécessaire au bon fonctionnement de notre contrôleur)
     *
     * @param Pdf $pdf
     */
    public function __construct(Pdf $pdf)
    {
        $this->pdfService = $pdf;
    }

    /**
     * Fonction permettant de récupérer les données météo ainsi que de les traiter pour la vue et export.
     *
     * @param Composant $composant
     * @param int       $annee
     *
     * @return array
     */
    private function recuperationDonneesMeteo(Composant $composant, int $annee): array
    {
        // On crée deux dates
        $tz = new \DateTimeZone('Europe/Paris');
        $debutAnnee = \DateTime::createFromFormat('Y-m-d H:i:s', $annee . '-01-01 00:00:00', $tz);
        $finAnnee = \DateTime::createFromFormat('Y-m-d H:i:s', $annee . '-12-31 23:59:59', $tz);

        // On va chercher les valeurs depuis la base de données
        $meteoAnnuelle = $this->getDoctrine()->getRepository(Composant::class)->meteoAnnuelle($composant, $annee);

        // On traite les informations de la base de données
        $tableauMeteo = [];
        foreach ($meteoAnnuelle as $meteoComposant) {
            // On crée un tableau d'évènements en fonction des périodes de MeteoComposant
            $evenements = [];
            foreach ($meteoComposant->getComposant()->getEvenementsMeteo() as $evenement) {
                if ($evenement->getDebut() <= $meteoComposant->getPeriodeFin() &&
                    $evenement->getFin() >= $meteoComposant->getPeriodeDebut()
                ) {
                    // On gère les bordures de dates (on force un affichage 01/01 si < à 01/01 etc...)
                    $periodeDebut = (clone $evenement->getDebut())->setTimeZone($tz);
                    $periodeFin = (clone $evenement->getFin())->setTimeZone($tz);
                    if ($periodeDebut < $debutAnnee) {
                        $periodeDebut = $debutAnnee;
                    }
                    if ($periodeFin > $finAnnee) {
                        $periodeFin = $finAnnee;
                    }

                    // On ajoute l'évènement dans notre tableau
                    $evenements[] = [
                        'typeOperation' => $evenement->getTypeOperation()->getLabel(),
                        'natureImpact' => $evenement->getImpact()->getLabel(),
                        'periode' => $periodeDebut->format('d/m (H:i)') . ' au ' . $periodeFin->format('d/m (H:i)'),
                        'description' => $evenement->getDescription(),
                        'commentaire' => $evenement->getCommentaire(),
                    ];
                }
            }

            // On gère les bordures de dates (on force un affichage 01/01 si < à 01/01 etc...)
            $periodeDebut = (clone $meteoComposant->getPeriodeDebut())->setTimeZone($tz);
            $periodeFin = (clone $meteoComposant->getPeriodeFin())->setTimeZone($tz);
            if ($periodeDebut < $debutAnnee) {
                $periodeDebut = $debutAnnee;
            }
            if ($periodeFin > $finAnnee) {
                $periodeFin = $finAnnee;
            }

            // On formate notre tableau de météo pour y ajouter toutes les informations dont nous aurons besoin dans la vue / exports
            $tableauMeteo[] = [
                'periode' => ChaineDeCaracteres::periodesEnLectureHumaine($periodeDebut, $periodeFin, false, '%s au %s'),
                'indice' => $meteoComposant->getMeteo(),
                'disponibilite' => $meteoComposant->getDisponibilite(),
                'evenements' => $evenements
            ];
        }

        return $tableauMeteo;
    }

    /**
     * @Route(
     *     path="/meteo/statistiques/historique/{serviceExploitant?}/{composant?}/{annee?}/{export?}",
     *     name="meteo-statistiques-historique",
     *     requirements={"export"="|xlsx|pdf"}
     * )
     */
    public function historiqueMeteoComposants(Service $serviceExploitant = null, Composant $composant = null, int $annee = null, string $export = null) : Response
    {
        // On initialise nos données ainsi que notre formulaire
        $tableauMeteo = false;
        $formFiltres = $this->createForm(HistoriqueType::class);

        // Si tous les champs sont remplis
        if ($serviceExploitant && $composant && $annee) {
            // On soumet notre formulaire avec les éléments de l'url
            $formFiltres->submit([
                'exploitant' => $serviceExploitant->getId(),
                'composant' => $composant->getId(),
                'annee' => $annee,
            ]);

            // Si le formulaire est valide
            if ($formFiltres->isValid()) {
                // On va chercher les valeurs depuis la base de données, que l'on prépare pour l'affichage
                $tableauMeteo = $this->recuperationDonneesMeteo($composant, $annee);

                // Si nous sommes dans le cas d'un export XLSX, on passe le relai à la fonction en charge de cet export
                if ($export === self::EXPORT_XLSX) {
                    return $this->exportXlsx($formFiltres->getData(), $tableauMeteo);

                // Si nous sommes dans le cas d'un export PDF, on passe le relai à la fonction en charge de cet export
                } elseif ($export === self::EXPORT_PDF) {
                    return $this->exportPdf($formFiltres->getData(), $tableauMeteo);
                }

                // Sinon, on laisse filer l'exécution pour générer la vue
            }

        // Improbable d'arriver dans un cas ou tous les éléments ne sont pas rempli mais au cas où on redirige.
        } elseif ($serviceExploitant || $composant || $annee) {
            return $this->redirectToRoute('meteo-statistiques-historique');
        }

        // On renvoi la vue historique
        return $this->render(
            'meteo/statistiques/historique-annuel.html.twig',
            [
                'form' => $formFiltres->createView(),
                'donnees' => $tableauMeteo
            ]
        );
    }

    /**
     * Fonction permettant de gérer l'export Xlsx.
     *
     * @param array $formData
     * @param array $donnees
     *
     * @return StreamedResponse
     */
    private function exportXlsx(array $formData, array $donnees) : StreamedResponse
    {
        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $annee = $formData['annee'];
        $composant = $formData['composant'];
        $labelComposant = $formData['composant']->getLabel();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("Historique du composant_{$annee}")
            ->setSubject("Export météo de l'année {$annee} pour le composant {$labelComposant}}")
            ->setDescription("Export météo: résumé des indisponibilités du composant sur l'année");

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
            'font' => [ 'bold'  => false, 'size'  => 11 ],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $activeWorksheet = $spreadsheet->getActiveSheet()->setTitle('Météo ' . $annee);

        // Ajoute le titre du composant représentant le label du composant
        $activeWorksheet->getCell('A1')
            ->setValue('Météo de ' . $composant->getLabel() . ' pour ' . $annee)
            ->getStyle()->applyFromArray($header1);
        $activeWorksheet->getRowDimension(1)->setRowHeight(35);
        $activeWorksheet->mergeCells('A1:H1');

        // On utilise un buffer pour injecter les colonnes du tableau
        $colonnesBuffer = [
            [
                'Semaine météo',
                'Indice',
                'Taux de disponibilité',
                'Période de l\'évènement',
                'Type d\'opération',
                'Impact',
                'Description',
                'Commentaire'
            ],
        ];
        $activeWorksheet->fromArray($colonnesBuffer, null, 'A2')
            ->getStyle('A2:H2')
            ->applyFromArray($header2);
        $activeWorksheet->getRowDimension(2)->setRowHeight(25);

        // Redimensionne toutes les cellules automatiquement (ou pas)
        for ($ascii = ord('A'); $ascii <= ord($activeWorksheet->getHighestColumn()); $ascii++) {
            if (chr($ascii) === 'G' || chr($ascii) === 'H') {
                $activeWorksheet->getColumnDimension(chr($ascii))->setWidth(50);
            } else {
                $activeWorksheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            }
        }

        // On parcourt nos données pour les restituer dans notre export xlxs
        $donneesBuffer = [];
        $offsetLigne = 3;
        foreach ($donnees as $meteoComposant) {
            // Si la météo possède des évènements
            if (count($meteoComposant['evenements']) > 0) {
                foreach ($meteoComposant['evenements'] as $idx => $evenement) {
                    // Période
                    $activeWorksheet->getCellByColumnAndRow(1, $offsetLigne)->setValue($meteoComposant['periode']);
                    // Image
                    $imagePath = $this->getParameter('kernel.project_dir') . "/public/assets/img/meteo-{$meteoComposant['indice']}.png";
                    if (is_file($imagePath) && is_readable($imagePath)) {
                        (new Drawing())
                            ->setPath($imagePath)
                            ->setWidth(30)
                            ->setHeight(30)
                            ->setOffsetX(15)
                            ->setCoordinates('B' . $offsetLigne)
                            ->setWorksheet($activeWorksheet);
                    }
                    // Taux de disponibilité
                    $activeWorksheet->getCellByColumnAndRow(3, $offsetLigne)->setValue(
                        str_replace('.', ',', $meteoComposant['disponibilite']) . '%'
                    );
                    // Configuration de la hauteur et du style de la ligne
                    $activeWorksheet->getRowDimension($offsetLigne)->setRowHeight(25);
                    $activeWorksheet->getStyle('A' . $offsetLigne . ':H' . $offsetLigne)->applyFromArray($header3);

                    // On affiche notre évènement
                    $activeWorksheet->fromArray([
                        $evenement['periode'],
                        $evenement['typeOperation'],
                        $evenement['natureImpact'],
                        $evenement['description'],
                        $evenement['commentaire'],
                    ], null, 'D' . $offsetLigne);
                    $activeWorksheet->getRowDimension($offsetLigne)->setRowHeight(25);
                    $activeWorksheet->getStyle('A' . $offsetLigne . ':H' . $offsetLigne)->applyFromArray($header3);
                    $offsetLigne++;
                }
            // Sinon
            } else {
                // Période
                $activeWorksheet->getCellByColumnAndRow(1, $offsetLigne)->setValue($meteoComposant['periode']);
                // Image
                $imagePath = $this->getParameter('kernel.project_dir') . "/public/assets/img/meteo-{$meteoComposant['indice']}.png";
                if (is_file($imagePath) && is_readable($imagePath)) {
                    (new Drawing())
                        ->setPath($imagePath)
                        ->setWidth(30)
                        ->setHeight(30)
                        ->setOffsetX(15)
                        ->setCoordinates('B' . $offsetLigne)
                        ->setWorksheet($activeWorksheet);
                }
                // Taux de disponibilité
                $activeWorksheet->getCellByColumnAndRow(3, $offsetLigne)->setValue(
                    str_replace('.', ',', $meteoComposant['disponibilite']) . '%'
                );
                // Configuration de la hauteur et du style de la ligne
                $activeWorksheet->getRowDimension($offsetLigne)->setRowHeight(25);
                $activeWorksheet->getStyle('A' . $offsetLigne . ':H' . $offsetLigne)->applyFromArray($header3);

                // On affiche "aucun évènement"
                $activeWorksheet->getCellByColumnAndRow(4, $offsetLigne)->setValue("Aucun évènement à afficher pour cette période.");
                $activeWorksheet->mergeCellsByColumnAndRow(4, $offsetLigne, 8, $offsetLigne);
                $offsetLigne++;
            }
        }

        // Ouvre un flux pour envoyer la réponse
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        // On détermine le nom du fichier
        $filename = sprintf(
            "export_meteo_%s_%s.xlsx",
            mb_strtolower($formData['composant']->getLabel()),
            $formData['annee']
        );

        // Défini les headers
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"{$filename}\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }

    /**
     * Fonction permettant de gérer l'export pdf.
     *
     * @param array $formData
     * @param array $donnees
     *
     * @return PdfResponse
     */
    private function exportPdf(array $formData, array $donnees) : PdfResponse
    {
        // On génère une vue avec les résultats
        $html = $this->renderView('meteo/statistiques/historique-annuel.pdf.html.twig', [
            'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
            'formData' => $formData,
            'donnees' => $donnees
        ]);

        // On en fait un pdf que l'on envoie
        return new PdfResponse(
            $this->pdfService->getOutputFromHtml(
                $html,
                [
                    'orientation' => 'Landscape',
                    'default-header' => true
                ]
            ),
            sprintf(
                "export_meteo_%s_%s.pdf",
                mb_strtolower($formData['composant']->getLabel()),
                $formData['annee']
            )
        );
    }
}
