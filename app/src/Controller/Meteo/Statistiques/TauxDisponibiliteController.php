<?php

namespace App\Controller\Meteo\Statistiques;

use App\Entity\Composant;
use App\Entity\Meteo\Evenement;
use App\Entity\Service;
use App\Form\Meteo\Statistiques\TauxDisponibiliteType;
use App\Utils\CalculatriceDisponibilite;
use App\Utils\ChaineDeCaracteres;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class TauxDisponibiliteController extends AbstractController
{
    /** @var Security */
    private $security;

    /**
     * TauxDisponibiliteController constructor.
     *
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @Route("/meteo/statistiques/taux-disponibilite/{serviceExploitant?}/{periodeDebut?}/{periodeFin?}/{exportType?}", name="meteo-statistiques-taux-disponibilite")
     */
    public function index(Request $request, ?Service $serviceExploitant, ?string $periodeDebut, ?string $periodeFin, ?string $exportType, Pdf $pdf): Response
    {
        // On initialise quelques variables
        $em = $this->getDoctrine()->getManager();
        $donnees = false;

        // On crée notre formulaire TauxDisponibiliteType
        $formFiltres = $this->createForm(TauxDisponibiliteType::class);

        // Si nous avons les des éléments dans l'url
        if ($serviceExploitant && $periodeDebut && $periodeFin) {
            // On récupère les périodes
            $tz = new \DateTimeZone('Europe/Paris');
            $periodeDebut = \DateTime::createFromFormat('Ymd', $periodeDebut, $tz);
            $periodeFin = \DateTime::createFromFormat('Ymd', $periodeFin, $tz);

            // On vérifie notre formulaire
            $formFiltres->submit([
                'exploitant' => $serviceExploitant->getId(),
                'debut' => $periodeDebut->format('d/m/Y'),
                'fin' => $periodeFin->format('d/m/Y'),
            ]);

            // Si le formulaire est valide
            if ($formFiltres->isValid()) {
                // On initialise nos données
                $donnees = [];
                $calculateurs = [];

                // On récupère la liste des composants correspondants à l'exploitant et on parcourt la liste pour créer
                //  les calculateurs de disponibilités sur la période.
                $composants = $em->getRepository(Composant::class)->listeComposantsParExploitant($serviceExploitant);
                foreach ($composants as $indx => $composant) {
                    $donnees[$composant->getId()] = [
                        'label' => $composant->getLabel(),
                        'calculateur' => new CalculatriceDisponibilite($periodeDebut, $periodeFin, $composant->getPlagesUtilisateur()->toArray()),
                    ];
                }

                // On récupère les évènements météos de la période pour les composants récupérés
                $idsComposants = array_keys($donnees);
                $evenements = $em->getRepository(Evenement::class)->listeEvenements($idsComposants, $periodeDebut, $periodeFin);
                // Liste des impacts qui ne sont pas a prendre en compte dans le calcul
                $impactsNonPrisEnCompte = [
                    'Aucun impact',
                    'Transparent pour les utilisateurs',
                    'Impact ponctuel MMA'
                ];
                /** @var Evenement $evenement */
                foreach ($evenements as $evenement) {
                    if (!in_array($evenement->getImpact()->getLabel(), $impactsNonPrisEnCompte)) {
                        $donnees[$evenement->getComposant()->getId()]['calculateur']->ajoutIndisponibilite($evenement->getDebut(), $evenement->getFin());
                    }
                }
            }
        } elseif ($serviceExploitant || $periodeDebut || $periodeFin) {
            // Improbable d'arriver dans un cas ou tous les éléments ne sont pas rempli mais au cas où on redirige.
            return $this->redirectToRoute('meteo-statistiques-taux-disponibilite');
        }


        // Si l'export demandé est un fichier pdf
        if ($exportType == 'pdf') {
            // On calcule les chaine pour l'affichage de la période
            $periodeDebutStr = $periodeDebut->format('d');
            $periodeFinStr = $periodeFin->format('d/m/Y');
            if ($periodeDebut->format('Y') !== $periodeFin->format('Y')) {
                $periodeDebutStr = $periodeDebut->format('d/m/Y');
            } elseif ($periodeDebut->format('m') !== $periodeFin->format('m')) {
                $periodeDebutStr = $periodeDebut->format('d/m');
            }

            // On génère une vue avec les résultats
            $html = $this->renderView('meteo/statistiques/taux-disponibilite.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
                'periode' => [
                    'debut' => $periodeDebutStr,
                    'fin' => $periodeFinStr
                ],
                'serviceExploitant' => $serviceExploitant,
                'donnees' => $donnees,
            ]);

            // On en fait un pdf que l'on transmet renvoie
            return new PdfResponse(
                $pdf->getOutputFromHtml($html),
                'export.pdf'
            );

        // Ou si l'export demandé est un fichier xlsx
        } elseif ($exportType == 'xlsx') {
            // Construit le fichier xlsx
            $spreadsheet = new Spreadsheet();
            $dateDebutString = $periodeDebut->format('d/m/Y');
            $dateFinString = $periodeFin->format('d/m/Y');
            $spreadsheet->getProperties()
                ->setCreator('Gesip')
                ->setTitle("Taux de disponibilité des composants")
                ->setSubject("Du {$dateDebutString} au {$dateFinString} pour {$serviceExploitant->getLabel()}")
                ->setDescription("Taux de disponibilité des composants");

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

            // On déclare la première feuille dans le fichier
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->setTitle("Du {$periodeDebut->format('Ymd')} au {$periodeFin->format('Ymd')}");

            // 1ère et 2ème lignes
            $activeSheet->getCell('A1')->setValue("Taux de disponibilité des composants")->getStyle()->applyFromArray($header1);
            $activeSheet->mergeCells('A1:D1');
            $activeSheet->getCell('A2')->setValue("Du {$dateDebutString} au {$dateFinString} pour {$serviceExploitant->getLabel()}")->getStyle()->applyFromArray($header2);
            $activeSheet->mergeCells('A2:D2');
            $activeSheet->getRowDimension(1)->setRowHeight(25);
            $activeSheet->getRowDimension(2)->setRowHeight(25);

            // 3ère ligne
            $activeSheet->getCell('A3')->setValue('Composant')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('B3')->setValue('Taux de disponibilité')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('C3')->setValue('Disponibilité attendue')->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('D3')->setValue('Indisponibilité déclarée')->getStyle()->applyFromArray($header3);
            $activeSheet->getRowDimension(3)->setRowHeight(25);

            // On parcourt les données
            $idx = 4;
            foreach ($donnees as $donnee) {
                $activeSheet->getCell("A$idx")->setValue($donnee['label']);
                $activeSheet->getCell("B$idx")->setValue(str_replace('.', ',', $donnee['calculateur']->getTauxDisponibilite()) . '%');
                $activeSheet->getCell("C$idx")->setValue(ChaineDeCaracteres::minutesEnLectureHumaineSimple($donnee['calculateur']->getDureeDisponibiliteTheoriqueMinutes()));
                $activeSheet->getCell("D$idx")->setValue(ChaineDeCaracteres::minutesEnLectureHumaineSimple($donnee['calculateur']->getDureeIndisponibiliteRelleMinutes()));
                $idx++;
            }

            // On calcule automatiquement les dimensions des cellules en fonction du contenu
            for ($ascii = ord('A'); $ascii <= ord('D'); $ascii++) {
                $activeSheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            }

            // Ouvre un flux pour envoyer la réponse
            $writer = new Xlsx($spreadsheet);
            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });

            // Défini les headers qui vont bien
            $response->headers->set('Content-Type', 'application/vnd.ms-excel');
            $response->headers->set('Content-Disposition', "attachment;filename=\"export_{$periodeDebut->format('Ymd')}-{$periodeFin->format('Ymd')}.xlsx\"");
            $response->headers->set('Cache-Control', 'max-age=0');

            // Envoi la réponse
            return $response;
        }

        // Sinon on affiche la vue normale
        return $this->render('meteo/statistiques/taux-disponibilite.html.twig', [
            'formFiltres' => $formFiltres->createView(),
            'donnees' => $donnees
        ]);
    }
}
