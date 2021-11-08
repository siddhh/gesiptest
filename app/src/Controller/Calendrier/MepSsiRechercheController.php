<?php

namespace App\Controller\Calendrier;

use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Entity\Operation;
use App\Entity\Composant;
use App\Entity\Pilote;
use App\Entity\Service;
use App\Form\Calendrier\RechercheMepSsiType;
use App\Service\OperationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class MepSsiRechercheController extends AbstractController
{
    const EXPORT_XLSX = 'xlsx';
    const EXPORT_PDF = 'pdf';

    /** @var Pdf */
    private $pdfService;

    /** @var EntityManagerInterface */
    private $em;

    /**
     * Construteur de MepSsiRechercheController
     *
     * @param EntityManagerInterface $em
     * @param Pdf                    $pdfService
     */
    public function __construct(EntityManagerInterface $em, Pdf $pdfService)
    {
        $this->em = $em;
        $this->pdfService = $pdfService;
    }

    /**
     * @Route(
     *     "/calendrier/mep-ssi/recherche/{debut?}/{fin?}/{exportType?}",
     *     name="calendrier-mep-ssi-recherche",
     *     requirements={
     *          "debut"="\d{4}-\d{2}-\d{2}",
     *          "fin"="\d{4}-\d{2}-\d{2}",
     *          "export"="xlsx|pdf"
     *     }
     * )
     */
    public function index(Request $request, OperationService $operationService, ?string $debut, ?string $fin, ?string $exportType): Response
    {
        // On initialise quelques variables
        $listeOperations = [];
        $periodeDebut = null;
        $periodeFin = null;

        // On crée notre formulaire de recherche
        $formFiltres = $this->createForm(RechercheMepSsiType::class);
        $formFiltres->handleRequest($request);

        // Si nous avons des informations de période dans l'url
        if (!$formFiltres->isSubmitted() && $debut !== null && $fin !== null) {
            $tz = new \DateTimeZone('Europe/Paris');
            $periodeDebut = \DateTime::createFromFormat('Y-m-d', $debut)->setTime(0, 0, 0)->setTimezone($tz);
            $periodeFin = \DateTime::createFromFormat('Y-m-d', $fin)->setTime(0, 0, 0)->setTimezone($tz);

            $formFiltres->setData([ 'periodeDebut' => $periodeDebut, 'periodeFin' => $periodeFin ]);
            $formFiltres->submit([ 'periodeDebut' => $periodeDebut->format('d/m/Y'), 'periodeFin' => $periodeFin->format('d/m/Y') ]);
        }

        // Si le formulaire est valide, on récupère les périodes saisies
        if ($formFiltres->isSubmitted() && $formFiltres->isValid()) {
            // On récupère les périodes
            $periodeDebut = $formFiltres->get('periodeDebut')->getData();
            $periodeFin = $formFiltres->get('periodeFin')->getData();
        }

        // Si nous avons des périodes, alors on peut aller chercher les résultats !
        if ($periodeDebut !== null && $periodeFin !== null) {
            // On récupère nos opérations
            $periodeFin->setTime(23, 59, 59);
            $operations = $operationService->findAllSurPeriode($periodeDebut, $periodeFin);

            // Que l'on parcourt
            foreach ($operations as $ope) {
                // On enveloppe notre object avec l'entité factice Operation (pour faciliter l'utilisation future dans l'affichage)
                $operation = new Operation($ope);

                // Si c'est une demande d'intervention, alors on itère par impacts (car la restitution se fait par impact).
                if ($operation->getOriginalClass() === DemandeIntervention::class) {
                    foreach ($operation->getOriginal()->getImpacts() as $impact) {
                        // On ajoute l'impact que l'on souhaite mettre en avant ici.
                        $listeOperations[] = new Operation($ope, $impact);
                    }

                    // Sinon, alors la restitution ne pose pas de soucis et nous pouvons donc ajouter facilement l'operation directement.
                } elseif ($operation->getOriginalClass() === MepSsi::class) {
                    $listeOperations[] = $operation;
                }
            }

            // On tri l'ordre de restitution en fonction des dates et heures
            usort($listeOperations, function ($a, $b) {
                return ($a->getDateTri() < $b->getDateTri()) ? -1 : 1;
            });
        }

        // Si nous voulons un export PDF
        if ($exportType === self::EXPORT_PDF) {
            $filtres = $request->query->all();
            $verifTypeFiltre = function ($type) {
                return in_array($type, [Operation::TYPE_MEPSSI, Operation::TYPE_GESIP]);
            };
            return $this->exportPdf($periodeDebut, $periodeFin, $listeOperations, [
                'type'              => (array_key_exists('type', $filtres) ? array_filter($filtres['type'], $verifTypeFiltre) : []),
                'exploitant'        => (array_key_exists('exploitant', $filtres) ? $this->em->getRepository(Service::class)->find(intval($filtres['exploitant'])) : null),
                'equipe'            => (array_key_exists('equipe', $filtres) ? $this->em->getRepository(Service::class)->find(intval($filtres['equipe'])) : null),
                'composant'         => (array_key_exists('composant', $filtres) ? $this->em->getRepository(Composant::class)->find(intval($filtres['composant'])) : null),
                'pilote'            => (array_key_exists('pilote', $filtres) ? $this->em->getRepository(Pilote::class)->find(intval($filtres['pilote'])) : null),
                'composantImpacte'  => (array_key_exists('composantImpacte', $filtres) ? $this->em->getRepository(Composant::class)->find(intval($filtres['composantImpacte'])) : null),
                'demandeur'         => (array_key_exists('demandeur', $filtres) ? $this->em->getRepository(Service::class)->find(intval($filtres['demandeur'])) : null)
            ]);

        // Si nous voulons un export XLSX
        } elseif ($exportType === self::EXPORT_XLSX) {
            return $this->exportXlsx($periodeDebut, $periodeFin, $listeOperations);
        }

        // On renvoie la vue
        return $this->render('/calendrier/mepssi/recherche.html.twig', [
            'formFiltres' => $formFiltres->createView(),
            'periode' => [
                'debut' => $periodeDebut ?? null,
                'fin' => $periodeFin ?? null,
            ],
            'operations' => $listeOperations,
        ]);
    }

    /**
     * Fonction permettant de gérer l'export pdf.
     *
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     * @param array     $operations
     * @param array     $filtres
     *
     * @return PdfResponse
     */
    private function exportPdf(\DateTime $periodeDebut, \DateTime $periodeFin, array $operations, array $filtres) : PdfResponse
    {
        // On filtre les opérations
        $operationsFiltrees = [];
        foreach ($operations as $operation) {
            if (!empty($filtres['type'])) {
                $operationClass = $operation->getOriginalClass();
                if (($operationClass === MepSsi::class && !in_array(Operation::TYPE_MEPSSI, $filtres['type']))
                    || ($operationClass === DemandeIntervention::class && !in_array(Operation::TYPE_GESIP, $filtres['type']))) {
                    continue;
                }
            } else {
                continue;
            }
            if ($filtres['exploitant'] != null) {
                $exploitants = [];
                foreach ($operation->getExploitants() as $annuaire) {
                    $exploitants[] = $annuaire->getService();
                }
                if (!in_array($filtres['exploitant'], $exploitants)) {
                    continue;
                }
            }
            if (($filtres['equipe'] != null) &&  ($filtres['equipe'] != $operation->getEquipe())) {
                continue;
            }
            if (($filtres['composant'] != null) &&  !in_array($filtres['composant'], $operation->getComposants())) {
                continue;
            }
            if (($filtres['pilote'] != null) &&  !in_array($filtres['pilote'], $operation->getPilotes())) {
                continue;
            }
            if (($filtres['composantImpacte'] != null) &&  !in_array($filtres['composantImpacte'], $operation->getComposantsImpactes())) {
                continue;
            }
            if (($filtres['demandeur'] != null) &&  ($filtres['demandeur'] != $operation->getDemandeur())) {
                continue;
            }
            $operationsFiltrees[] = $operation;
        }

        // On génère une vue avec les résultats
        $html = $this->renderView('/calendrier/mepssi/recherche.pdf.html.twig', [
            'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
            'periode' => [
                'debut' => $periodeDebut ?? null,
                'fin' => $periodeFin ?? null,
            ],
            'operations' => $operationsFiltrees,
            'filtres'    => $filtres
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
                "export_%s_%s.pdf",
                $periodeDebut->format('Ymd'),
                $periodeFin->format('Ymd')
            )
        );
    }

    /**
     * Génère une réponse sous forme d'un fichier xlsx
     *
     * @param \DateTime $periodeDebut
     * @param \DateTime $periodeFin
     * @param array     $operations
     *
     * @return Response
     */
    private function exportXlsx(\DateTime $periodeDebut, \DateTime $periodeFin, array $operations): Response
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
        $styleValueCell = [
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true ]
        ];

        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("Consulter le calendrier des interventions et des MEP SSI")
            ->setSubject("Consulter le calendrier des interventions et des MEP SSI")
            ->setDescription('Consulter le calendrier des interventions et des MEP SSI.');

        // Récupère l'onglet courant et lui donne un titre
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setTitle("Consultation");

        // 1ère et 2ème lignes
        $activeWorksheet->getCell('A1')->setValue("Consulter le calendrier des interventions et des MEP SSI")->getStyle()->applyFromArray($header1);
        $activeWorksheet->mergeCells('A1:G1');
        $activeWorksheet->getCell('A2')->setValue("Composants ")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('B2')->setValue("Intervention/MEP")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('C2')->setValue("Impact/Description")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('D2')->setValue("Palier")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('E2')->setValue("Equipe")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('F2')->setValue("Pilotes")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getCell('G2')->setValue("ESI")->getStyle()->applyFromArray($header2);
        $activeWorksheet->getRowDimension(1)->setRowHeight(25);
        $activeWorksheet->getRowDimension(2)->setRowHeight(30);
        $decallageLigne = 3;

        foreach ($operations as $operation) {
            $activeWorksheet->getCell("A$decallageLigne")->setValue($operation->donneesComposant())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getCell("B$decallageLigne")->setValue($operation->donneesInterventionMep())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getCell("C$decallageLigne")->setValue($operation->donneesImpactDescription())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getCell("D$decallageLigne")->setValue($operation->donneesPalier())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getCell("E$decallageLigne")->setValue($operation->donneesEquipe())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getCell("F$decallageLigne")->setValue($operation->donneesPilote())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getCell("G$decallageLigne")->setValue($operation->donneesEsi())->getStyle()->applyFromArray($styleValueCell);
            $activeWorksheet->getRowDimension($decallageLigne)->setRowHeight(-1);
            $decallageLigne++;
        }

        // On calcule automatiquement les dimensions des cellules en fonction du contenu
        for ($ascii = ord('A'); $ascii <= ord('G'); $ascii++) {
            if ($ascii == ord('A') || $ascii == ord('B') || $ascii == ord('C')) {
                $activeWorksheet->getColumnDimension(chr($ascii))->setWidth(50);
            } else {
                $activeWorksheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            }
        }

        // Ouvre un flux pour envoyer la réponse
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        // On génère notre nom de fichier
        $filename = sprintf(
            "export_%s_%s.xlsx",
            $periodeDebut->format('Ymd'),
            $periodeFin->format('Ymd')
        );

        // Défini les headers qui vont bien
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"$filename\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }
}
