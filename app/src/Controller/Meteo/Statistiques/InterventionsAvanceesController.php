<?php

namespace App\Controller\Meteo\Statistiques;

use App\Entity\DemandeIntervention;
use App\Entity\References\MotifIntervention;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use App\Form\Meteo\Statistiques\InterventionsAvanceesType;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class InterventionsAvanceesController extends AbstractController
{
    const NO_EXPORT = 'no_export';
    const EXPORT_PDF = 'pdf';
    const EXPORT_XLSX = 'xlsx';

    /** @var Pdf */
    private $pdfService;

    /**
     * InterventionsAvanceesController constructor.
     *
     * @param Pdf $pdf
     */
    public function __construct(Pdf $pdf)
    {
        $this->pdfService = $pdf;
    }

    /**
     * @Route("/meteo/statistiques/interventions-avancees", name="meteo-statistiques-avancees")
     */
    public function index(Request $request): Response
    {
        $interventions = [];

        // On crée notre formulaire, et on traite la requête avec celui-ci.
        $form = $this->createForm(InterventionsAvanceesType::class);
        $form->handleRequest($request);

        // Si le formulaire est soumis et qu'il est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // On récupère les boutons de soumissions
            $btnStatistiques = $form->get('statistiquesVisualiser');
            $btnDynamiqueCroise = $form->get('croiseVisualiser');

            // On défini le type d'export (si on demande un export)
            $exportType = self::NO_EXPORT;
            $btnExportXlsx = $form->get('exportXLSX');
            $btnExportPdf = $form->get('exportPDF');
            if ($btnExportXlsx->isClicked()) {
                $exportType = self::EXPORT_XLSX;
            } elseif ($btnExportPdf->isClicked()) {
                $exportType = self::EXPORT_PDF;
            }

            // On demande à une autre fonction de prendre le relai, en fonction de l'action demandée
            if ($btnStatistiques->isClicked()) {
                return $this->tableauStatistiques($form->getData(), $exportType);
            } elseif ($btnDynamiqueCroise->isClicked()) {
                return $this->tableauDynamiqueCroise($form->getData(), $exportType);
            }

            // Sinon, c'est qu'il s'est passé un truc bizarre ...
            throw new NotFoundHttpException();
        }

        // On génère la vue formulaire
        return $this->render('meteo/statistiques/interventions-avancees/formulaire.html.twig', [
            'form' => $form->createView(),
            'interventions' => $interventions,
        ]);
    }

    /**
     * Permet de récupérer la liste des demandes d'interventions en fonction de certains critères passés en paramètre.
     *
     * @param array $formData
     * @return array
     */
    private function getDemandesInterventions(array $formData): array
    {
        // On charge les services, les motifs d'intervention ainsi que les nature d'impact
        $this->getDoctrine()->getRepository(Service::class)
            ->createQueryBuilder('s')->select(['partial s.{id, label}'])->getQuery()->getResult();
        $this->getDoctrine()->getRepository(MotifIntervention::class)
            ->createQueryBuilder('m')->select(['partial m.{id, label}'])->getQuery()->getResult();
        $this->getDoctrine()->getRepository(NatureImpact::class)
            ->createQueryBuilder('n')->select(['partial n.{id, label}'])->getQuery()->getResult();

        // On crée notre période
        $periodeDebut = \DateTime::createFromFormat('d/m/Y H:i:s', '01/01/' . $formData['periodeDebut'] . ' 00:00:00');
        $periodeFin = \DateTime::createFromFormat('d/m/Y H:i:s', '31/12/' . $formData['periodeFin'] . ' 00:00:00');

        // On crée la requête de base qui sera filtré en fonction des paramètres passés dans la fonction
        $repository = $this->getDoctrine()->getRepository(DemandeIntervention::class);
        $query = $repository->createQueryBuilder('demande')
            ->addSelect([
                'partial composant.{id, label}',
                'partial composantsImpactes.{id, label}',
                'partial impactsPrevisionnels.{id, nature}',
                'partial saisieRealises.{id}',
                'partial impactReels.{id, saisieRealise, nature, dateDebut, dateFin}',
            ])
            ->join('demande.composant', 'composant')
            ->leftJoin('composant.composantsImpactes', 'composantsImpactes')
            ->leftJoin('demande.historiqueStatus', 'historiqueStatus')
            ->leftJoin('demande.impacts', 'impactsPrevisionnels')
            ->leftJoin('demande.saisieRealises', 'saisieRealises')
            ->leftJoin('saisieRealises.impactReels', 'impactReels')
            ->where('demande.dateDebut <= :periodeFin AND demande.dateFinMax >= :periodeDebut')
            ->setParameter('periodeDebut', $periodeDebut)->setParameter('periodeFin', $periodeFin)
            ->andWhere('demande.supprimeLe IS NULL')
            ->andWhere('demande.status <> :etatBrouillon')->setParameter('etatBrouillon', EtatBrouillon::class)
        ;

        // Si filtrage par "demandeur"
        if ($formData['demandeur']) {
            $query->andWhere('demande.demandePar = :demandeur')->setParameter('demandeur', $formData['demandeur']);
        }
        // Si filtrage par "nature"
        if ($formData['nature']) {
            $query->andWhere('demande.natureIntervention = :nature')->setParameter('nature', $formData['nature']);
        }
        // Si filtrage par "composant"
        if ($formData['composant']) {
            $query->andWhere('demande.composant = :composant')->setParameter('composant', $formData['composant']);
        }
        // Si filtrage par "composant"
        if ($formData['motif']) {
            $query->andWhere('demande.motifIntervention = :motif')->setParameter('motif', $formData['motif']);
        }
        // Si filtrage par "exploitant"
        if ($formData['exploitant']) {
            $query->andWhere('composant.exploitant = :exploitant')->setParameter('exploitant', $formData['exploitant']);
        }
        // Si filtrage par "composant impacté"
        if ($formData['composantImpacte']) {
            $conditionsQueryBuilder = $repository->createQueryBuilder('d2')->select('d2.id');
            $subConditionsImpactComposantsQueryBuilder = $repository->createQueryBuilder('d3')
                ->select('d3.id')
                ->join('d3.impacts', 'i3')
                ->join('i3.composants', 'ic3')
                ->where('ic3.id = :composantImpacte');
            $subConditionsImpactReelsComposantsQueryBuilder = $repository->createQueryBuilder('d4')
                ->select('d4.id')
                ->join('d4.saisieRealises', 'sr4')
                ->join('sr4.impactReels', 'ir4')
                ->join('ir4.composants', 'irc4')
                ->where('irc4.id = :composantImpacte');
            $conditionsQueryBuilder->andWhere('(d2.id IN (' . $subConditionsImpactComposantsQueryBuilder->getDQL() . ')
                OR d2.id IN (' . $subConditionsImpactReelsComposantsQueryBuilder->getDQL() . '))');
            $query->andWhere('demande.id IN (' . $conditionsQueryBuilder->getDQL() . ')');
            $query->setParameter('composantImpacte', $formData['composantImpacte']);
        }
        // Si filtrage par "impact prévu"
        if ($formData['impactPrevu']) {
            $query->andWhere('impactsPrevisionnels.nature = :impactPrevu')->setParameter('impactPrevu', $formData['impactPrevu']);
        }
        // Si filtrage par "impact réel"
        if ($formData['impactReel']) {
            $query->andWhere('impactReels.nature = :impactReel')->setParameter('impactReel', $formData['impactReel']);
        }

        // Si filtrage par "décision DME" : on fait un premier filtrage (implique de voir l'historique pour le reste !)
        if ($formData['decisionDme']) {
            // Si Accord
            if ($formData['decisionDme'] === "Accord") {
                $statuts = [
                    EtatAccordee::class,
                    EtatInterventionEnCours::class,
                    EtatAnnulee::class, // (attention, il faut que ça soit annulée après accord !)
                    EtatSaisirRealise::class,
                    EtatInterventionEchouee::class,
                    EtatInterventionReussie::class,
                ];

            // Si Refus
            } elseif ($formData['decisionDme'] === "Refus") {
                $statuts = [
                    EtatRefusee::class,
                ];

            // Si "En attente"
            } elseif ($formData['decisionDme'] === "En attente") {
                $statuts = [
                    EtatAnalyseEnCours::class,
                    EtatConsultationEnCours::class,
                    EtatConsultationEnCoursCdb::class,
                    EtatInstruite::class,
                    EtatRenvoyee::class, // (attention, il faut que ça soit après analyse ou consultation ou accord !)
                ];
            }

            // Si il y a des status à chercher
            if (count($statuts) > 0) {
                $query->andWhere('demande.status IN (:status)')->setParameter('status', $statuts);
            }
        }

        // On récupère les données pour la suite
        $demandes = $query->getQuery()->getResult();

        // Si un filtrage par "décision DME = Accord" est demandé
        if ($formData['decisionDme'] && $formData['decisionDme'] === "Accord") {
            // On initialise notre résultat final
            $resultats = [];

            // On parcourt les demandes
            /** @var DemandeIntervention $demande */
            foreach ($demandes as $demande) {
                // Si l'état actuel est Annulée, il faut que la demande ait fait l'objet d'un accord
                if ($demande->getStatus() === EtatAnnulee::class) {
                    foreach ($demande->getHistoriqueStatus() as $historiqueStatus) {
                        if ($historiqueStatus->getStatus() === EtatAccordee::class) {
                            $resultats[] = $demande;
                            break;
                        }
                    }
                // Sinon, on doit ajouter la demande dans les résultats
                } else {
                    $resultats[] = $demande;
                }
            }

            // On renvoi nos demandes filtrés correctement
            return $resultats;
        }

        // Si un filtrage par "décision DME = Accord" est demandé
        if ($formData['decisionDme'] && $formData['decisionDme'] === "En attente") {
            // On initialise notre résultat final
            $resultats = [];

            // On parcourt les demandes
            /** @var DemandeIntervention $demande */
            foreach ($demandes as $demande) {
                // Si l'état actuel est Renvoyée, il faut que la demande ait fait l'objet d'une analyse, d'une consultation ou d'un accord
                if ($demande->getStatus() === EtatRenvoyee::class) {
                    foreach ($demande->getHistoriqueStatus() as $historiqueStatus) {
                        if (in_array($historiqueStatus->getStatus(), [
                                EtatAnalyseEnCours::class,
                                EtatConsultationEnCours::class,
                                EtatAccordee::class
                            ])) {
                            $resultats[] = $demande;
                            break;
                        }
                    }
                // Sinon, on doit ajouter la demande dans les résultats
                } else {
                    $resultats[] = $demande;
                }
            }

            // On renvoi les demandes filtrés correctement
            return $resultats;
        }

        // On renvoi les demandes filtrés (simplement)
        return $demandes;
    }

    /**
     * On génère le tableau statistiques.
     *
     * @param array $formData
     * @param string $exportType
     * @return Response
     */
    private function tableauStatistiques(array $formData, string $exportType): Response
    {
        // On initialise quelques variables utiles
        $tz = new \DateTimeZone('Europe/Paris');
        $donnees = [];

        // On récupère les demandes d'interventions à utiliser
        $demandes = $this->getDemandesInterventions($formData);

        // On défini les mois au format texte (comme on commence par l'index 0, on doit faire ($mois - 1) pour avoir la valeur)
        $moisStr = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];

        // Si on doit restituer par année / trimestres / mois, on doit préparer notre tableau de résultats avant tout
        if (preg_match("/^date-.*/", $formData['statistiquesRecherchePar'])) {
            // On boucle par années en fonction de la période choisie
            for ($annee = $formData['periodeDebut']; $annee <= $formData['periodeFin']; $annee++) {
                // Si c'est trimestriel ou mensuel
                if ($formData['statistiquesRecherchePar'] === "date-trimestres" || $formData['statistiquesRecherchePar'] === "date-mois") {
                    // On ajoute l'année début à chaque début d'année (si on est sur une période multi-années)
                    if ($formData['periodeDebut'] != $formData['periodeFin']) {
                        $donnees[$annee . '00-'] = [$annee, [0, 0], [0, 0]];
                    }

                    // Si trimestriel, on a un pas de 3 sinon de 1.
                    $pasMois = $formData['statistiquesRecherchePar'] === "date-trimestres" ? 3 : 1;

                    // On parcourt les mois de l'année avec 3 ou 1 en pas
                    for ($mois = 1; $mois <= 12; $mois += $pasMois) {
                        $mmois = str_pad($mois, 2, '0', STR_PAD_LEFT);
                        $str = $moisStr[$mois - 1] . ($pasMois == 3 ?' à ' . $moisStr[$mois + 1] : null);
                        $donnees[$annee . $mmois] = [$str, [0, 0], [0, 0]];
                    }

                // Sinon c'est annuel
                } else {
                    $donnees[$annee] = [$annee, [0, 0], [0, 0]];
                }
            }
        }

        // On parcourt toutes les demandes d'interventions
        /** @var DemandeIntervention $demande */
        foreach ($demandes as $demande) {
            // On initialise quelques variables
            $categories = [];

            // On sélectionne les différentes clées nécessaires à la restitution des statistiques
            //  afin notamment de pouvoir ajouter les données ont bons endroits dans le tableau de données final.
            switch ($formData['statistiquesRecherchePar']) {
                case 'demandeur':
                    $categories[$demande->getDemandePar()->getId()] = $demande->getDemandePar()->getLabel();
                    break;
                case 'nature':
                    $categories[$demande->getNatureIntervention()] = ucfirst($demande->getNatureIntervention());
                    break;
                case 'composant':
                    $categories[$demande->getComposant()->getId()] = $demande->getComposant()->getLabel();
                    break;
                case 'motif':
                    $categories[$demande->getMotifIntervention()->getId()] = $demande->getMotifIntervention()->getLabel();
                    break;
                case 'exploitant':
                    $categories[$demande->getComposant()->getExploitant()->getId()] = $demande->getComposant()->getExploitant()->getLabel();
                    break;
                case 'composant-impacte':
                    foreach ($demande->getComposant()->getComposantsImpactes() as $composant) {
                        $categories[$composant->getId()] = $composant->getLabel();
                    }
                    break;
                case 'impact-previsionnel':
                    foreach ($demande->getImpacts() as $impact) {
                        $categories[$impact->getNature()->getId()] = $impact->getNature()->getLabel();
                    }
                    break;
                case 'impact-reel':
                    foreach ($demande->getSaisieRealises() as $saisieRealise) {
                        foreach ($saisieRealise->getImpactReels() as $impactReel) {
                            $categories[$impactReel->getNature()->getId()] = $impactReel->getNature()->getLabel();
                        }
                    }
                    break;
                case 'date-mois':
                case 'date-trimestres':
                case 'date-annees':
                    // On récupère les dates de début et de fin que l'on manipule un peu pour faire une itération par mois
                    //  dans le cas d'une intervention sur plusieurs mois.
                    $dateDebut = \DateTime::createFromFormat('d/m/Y H:i:s', $demande->getDateDebut()->format('01/m/Y 00:00:00'), $tz);
                    $dateFin = \DateTime::createFromFormat('d/m/Y H:i:s', $demande->getDateFinMax()->format('01/m/Y 23:59:59'), $tz);
                    $periode = new \DatePeriod($dateDebut, new \DateInterval('P1M'), $dateFin);

                    // On parcourt notre période (par mois donc)
                    foreach ($periode as $date) {
                        // On récupère notre mois et année
                        $mois = $date->format('m');
                        $annee = $date->format('Y');

                        // Si nous sommes dans le cas d'un trimestre, nous mois de début de la période
                        if ($formData['statistiquesRecherchePar'] === "date-trimestres") {
                            $mois = $mois - (($mois - 1) % 3);
                        }

                        // Et on ajoute nos catégories
                        $categories[$annee] = true;
                        $categories[$annee.'00-'] = true;
                        $categories[$annee.$mois] = true;
                    }
                    break;
            }

            // Maintenant que nous savons comment catégoriser la demande, on peut faire les calculs qui vont avec
            for ($i = 1; $i <= 2; $i++) {
                switch ($formData['statistiquesStat' . $i]) {
                    case 'nbr-interventions':
                        $this->ajoutDonneesCategories($categories, $donnees, $formData, $i, 1, 0);
                        break;

                    case 'duree-moyenne-previsionnelle':
                        $this->ajoutDonneesCategories($categories, $donnees, $formData, $i, $demande->getDureePrevisionnelleMinutes());
                        break;
                    case 'duree-moyenne-reelle':
                        $this->ajoutDonneesCategories($categories, $donnees, $formData, $i, $demande->getDureeReelleMinutes());
                        break;
                    case 'delai-moyen-reponse-dme':
                        $this->ajoutDonneesCategories($categories, $donnees, $formData, $i, $demande->getDureeReponseDmeJours());
                        break;
                }
            }
        }

        // On parcourt toutes nos donnees pour y faire une moyenne (si possible!)
        foreach ($donnees as $key => $donnee) {
            for ($i = 1; $i <= 2; $i++) {
                if ($donnees[$key][$i][1] !== 0) {
                    $donnees[$key][$i] = round($donnees[$key][$i][0] / $donnees[$key][$i][1]);
                } else {
                    $donnees[$key][$i] = $donnees[$key][$i][0];
                }
            }
        }

        // On tri par la première valeur du tableau
        if (preg_match("/^date-.*/", $formData['statistiquesRecherchePar'])) {
            // Si par date, alors on tri en fonction des clés
            ksort($donnees);
        } else {
            // Sinon c'est par le label de "Recherche par"
            usort($donnees, function ($a, $b) {
                return $a[0] <=> $b[0];
            });
        }

        // Si nous n'avons pas demandé un export, nous renvoyons la vue normale
        if ($exportType === self::NO_EXPORT) {
            // On génère la vue
            return $this->render('meteo/statistiques/interventions-avancees/tableau-statistiques.html.twig', [
                'formType' => new InterventionsAvanceesType(),
                'formData' => $formData,
                'donnees' => $donnees
            ]);

        // Si export PDF, alors on génère le fichier pdf
        } elseif ($exportType === self::EXPORT_PDF) {
            // On génère la vue
            $html = $this->renderView('meteo/statistiques/interventions-avancees/tableau-statistiques.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
                'formType' => new InterventionsAvanceesType(),
                'formData' => $formData,
                'donnees' => $donnees
            ]);

            // On retourne notre pdf
            return new PdfResponse(
                $this->pdfService->getOutputFromHtml($html),
                'export.pdf'
            );

        // Si export XLSX, alors on génère le fichier xlsx
        } elseif ($exportType === self::EXPORT_XLSX) {
            // Construit le fichier xlsx
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Gesip')
                ->setTitle("Statistiques avancées - Tableau statistiques")
                ->setSubject("Du 01/01/{$formData['periodeDebut']} au 31/12/{$formData['periodeFin']}")
                ->setDescription("Statistiques avancées - Tableau statistiques");

            // Défini des styles utilisés dans le fichier excel
            $header1 = [
                'font'      => [
                    'bold'  => true,
                    'size'  => 13,
                    'color' => ['argb' => '0000CC']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true
                ]
            ];
            $header2 = [
                'font'      => [
                    'bold'  => true,
                    'size'  => 12,
                    'color' => ['argb' => '0000CC']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true
                ]
            ];
            $header3 = [
                'font'      => [
                    'bold' => true,
                    'size' => 11
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true
                ]
            ];

            // On déclare la première feuille dans le fichier
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->setTitle("Du 01-01-{$formData['periodeDebut']} au 31-12-{$formData['periodeFin']}");

            // 1ère ligne
            $activeSheet->getCell('A1')->setValue("Statistiques avancées")->getStyle()->applyFromArray($header1);
            $activeSheet->mergeCells($formData['statistiquesStat2'] ? 'A1:C1' : 'A1:B1');
            $activeSheet->getRowDimension(1)->setRowHeight(25);

            // 3ère ligne
            $activeSheet->getCell('A2')->setValue(InterventionsAvanceesType::getChoix1($formData['statistiquesRecherchePar']))->getStyle()->applyFromArray($header3);
            $activeSheet->getCell('B2')->setValue(InterventionsAvanceesType::getChoix2($formData['statistiquesStat1']))->getStyle()->applyFromArray($header3);
            if ($formData['statistiquesStat2']) {
                $activeSheet->getCell('C2')->setValue(InterventionsAvanceesType::getChoix2($formData['statistiquesStat2']))->getStyle()->applyFromArray($header3);
            }
            $activeSheet->getRowDimension(2)->setRowHeight(25);

            // On parcourt les données
            $idx = 3;
            foreach ($donnees as $donnee) {
                $activeSheet->getCell("A$idx")->setValue($donnee[0]);
                $activeSheet->getCell("B$idx")->setValue($donnee[1]);
                if ($formData['statistiquesStat2']) {
                    $activeSheet->getCell("C$idx")->setValue($donnee[2]);
                }
                $idx++;
            }

            // On calcule automatiquement les dimensions des cellules en fonction du contenu
            for ($ascii = ord('A'); $ascii <= ord($formData['statistiquesStat2'] ? 'C' : 'B'); $ascii++) {
                $activeSheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            }

            // Ouvre un flux pour envoyer la réponse
            $writer = new Xlsx($spreadsheet);
            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });

            // Défini les headers qui vont bien
            $response->headers->set('Content-Type', 'application/vnd.ms-excel');
            $response->headers->set('Content-Disposition', "attachment;filename=\"export_{$formData['periodeDebut']}-{$formData['periodeFin']}.xlsx\"");
            $response->headers->set('Cache-Control', 'max-age=0');

            // Envoi la réponse
            return $response;
        }
    }

    /**
     * Permet d'ajouter une valeur dans le tableau de données passé en paramètre.
     *
     * @param array $categories
     * @param array $donnees
     * @param array $formData
     * @param int   $statistique
     * @param int   $valeur
     * @param int   $comptage
     */
    private function ajoutDonneesCategories(array &$categories, array &$donnees, array $formData, int $statistique, int $valeur, int $comptage = 1) : void
    {
        foreach ($categories as $key => $label) {
            if (isset($donnees[$key])) {
                $donnees[$key][$statistique][0] += $valeur;
                $donnees[$key][$statistique][1] += $comptage;
            } elseif (!isset($donnees[$key]) && !preg_match("/^date-.*/", $formData['statistiquesRecherchePar'])) {
                $donnees[$key] = [$label, [0, 0], [0, 0]];
                $donnees[$key][$statistique][0] += $valeur;
                $donnees[$key][$statistique][1] += $comptage;
            }
        }
    }

    /**
     * On génère le tableau Dynamique croisé.
     *
     * @param array $formData
     * @param string $exportType
     * @return Response
     */
    private function tableauDynamiqueCroise(array $formData, string $exportType): Response
    {
        // On initialise quelques variables utiles
        $tz = new \DateTimeZone('Europe/Paris');
        $colonnes = [];
        $lignes = [];
        $donnees = [];

        // On récupère les interventions à utiliser
        $demandes = $this->getDemandesInterventions($formData);

        // On défini les mois au format texte (comme on commence par l'index 0, on doit faire ($mois - 1) pour avoir la valeur)
        $moisStr = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];

        // Si on doit restituer par année / trimestres / mois, on doit préparer notre tableau de résultats avant tout
        $typesEntete = ['croiseLigne' => &$lignes, 'croiseColonne' => &$colonnes];
        foreach ($typesEntete as $type => &$entete) {
            if (preg_match("/^date-.*/", $formData[$type])) {
                // On boucle par années en fonction de la période choisie
                for ($annee = $formData['periodeDebut']; $annee <= $formData['periodeFin']; $annee++) {
                    // Si c'est trimestriel ou mensuel
                    if ($formData[$type] === "date-trimestres" || $formData[$type] === "date-mois") {
                        // On ajoute l'année début à chaque début d'année (si on est sur une période multi-années)
                        if ($formData['periodeDebut'] != $formData['periodeFin'] || $type === 'croiseColonne') {
                            $entete[$annee . '00-'] = $annee;
                        }

                        // Si trimestriel, on a un pas de 3 sinon de 1.
                        $pasMois = $formData[$type] === "date-trimestres" ? 3 : 1;

                        // On parcourt les mois de l'année avec 3 ou 1 en pas
                        for ($mois = 1; $mois <= 12; $mois += $pasMois) {
                            $mmois = str_pad($mois, 2, '0', STR_PAD_LEFT);
                            $entete[$annee . $mmois] = $moisStr[$mois - 1] . ($pasMois == 3 ?' à ' . $moisStr[$mois + 1] : null);
                        }

                        // Sinon c'est annuel
                    } else {
                        $entete[$annee] = $annee;
                    }
                }
            }
        }

        // On parcourt toutes les demandes d'interventions
        /** @var DemandeIntervention $demande */
        foreach ($demandes as $demande) {
            // On initialise les coordonées
            $idX = [];
            $idY = [];

            // Gestion des lignes et colonnes
            for ($i = 0; $i <= 1; $i++) {
                // On initialise
                if ($i == 0) {
                    $source = 'croiseLigne';
                    $entete = &$lignes;
                    $coord = &$idX;
                } elseif ($i == 1) {
                    $source = 'croiseColonne';
                    $entete = &$colonnes;
                    $coord = &$idY;
                }

                // On définie nos coordonées tout en construisant nos entêtes
                //  (si $i = 0 les lignes, si $i = 1 les colonnes)
                switch ($formData[$source]) {
                    case 'demandeur':
                        $demandeur = $demande->getDemandePar();
                        $coord[] = $this->ajoutCroiseEntete($entete, $demandeur->getId(), $demandeur->getLabel());
                        break;
                    case 'nature':
                        $coord[] = $this->ajoutCroiseEntete($entete, $demande->getNatureIntervention(), ucfirst($demande->getNatureIntervention()));
                        break;
                    case 'composant':
                        $composant = $demande->getComposant();
                        $coord[] = $this->ajoutCroiseEntete($entete, $composant->getId(), $composant->getLabel());
                        break;
                    case 'motif':
                        $motif = $demande->getMotifIntervention();
                        $coord[] = $this->ajoutCroiseEntete($entete, $motif->getId(), $motif->getLabel());
                        break;
                    case 'exploitant':
                        $exploitant = $demande->getComposant()->getExploitant();
                        $coord[] = $this->ajoutCroiseEntete($entete, $exploitant->getId(), $exploitant->getLabel());
                        break;
                    case 'composant-impacte':
                        foreach ($demande->getComposant()->getComposantsImpactes() as $composant) {
                            $coord[] = $this->ajoutCroiseEntete($entete, $composant->getId(), $composant->getLabel());
                        }
                        break;
                    case 'impact-previsionnel':
                        foreach ($demande->getImpacts() as $impact) {
                            $nature = $impact->getNature();
                            $coord[] = $this->ajoutCroiseEntete($entete, $nature->getId(), $nature->getLabel());
                        }
                        break;
                    case 'impact-reel':
                        foreach ($demande->getSaisieRealises() as $saisieRealise) {
                            foreach ($saisieRealise->getImpactReels() as $impact) {
                                $nature = $impact->getNature();
                                $coord[] = $this->ajoutCroiseEntete($entete, $nature->getId(), $nature->getLabel());
                            }
                        }
                        break;
                    case 'date-mois':
                    case 'date-trimestres':
                    case 'date-annees':
                        // On récupère les dates de début et de fin que l'on manipule un peu pour faire une itération par mois
                        //  dans le cas d'une intervention sur plusieurs mois.
                        $dateDebut = \DateTime::createFromFormat('d/m/Y H:i:s', $demande->getDateDebut()->format('01/m/Y 00:00:00'), $tz);
                        $dateFin = \DateTime::createFromFormat('d/m/Y H:i:s', $demande->getDateFinMax()->format('01/m/Y 23:59:59'), $tz);
                        $periode = new \DatePeriod($dateDebut, new \DateInterval('P1M'), $dateFin);

                        // On parcourt notre période (par mois donc)
                        foreach ($periode as $date) {
                            // On récupère notre mois et année
                            $mois = $date->format('m');
                            $annee = $date->format('Y');

                            // Si nous sommes dans le cas d'un trimestre, nous mois de début de la période
                            if ($formData[$source] === "date-trimestres") {
                                $mois = $mois - (($mois - 1) % 3);
                            }

                            // Et on ajoute nos catégories
                            $coord[] = $annee;
                            $coord[] = $annee.'00-';
                            $coord[] = $annee.$mois;
                        }
                        break;
                }
            }

            // On récupère la valeur à ajouter en fonction de $formData['croiseValeur']
            switch ($formData['croiseValeur']) {
                default:
                    $valeur = 0;
                    break;
                case 'nbr-interventions':
                    $valeur = 1;
                    break;
                case 'duree-moyenne-previsionnelle':
                    $valeur = $demande->getDureePrevisionnelleMinutes();
                    break;
                case 'duree-moyenne-reelle':
                    $valeur = $demande->getDureeReelleMinutes();
                    break;
                case 'delai-moyen-reponse-dme':
                    $valeur = $demande->getDureeReponseDmeJours();
                    break;
            }

            // Maintenant que les coordonnées sont définies et qu'on a la valeur,
            // il n'y a plus qu'à ajouter la valeur précédente au bons endroits (si la valeur n'est pas 0) !
            if ($valeur !== 0) {
                foreach ($idX as $x) {
                    foreach ($idY as $y) {
                        $coordonnees = $x . '|' . $y;
                        if (!isset($donnees[$coordonnees])) {
                            $donnees[$coordonnees] = [0, 0];
                        }
                        $donnees[$coordonnees][0] += $valeur;
                        $donnees[$coordonnees][1] += $formData['croiseValeur'] === 'nbr-interventions' ? 0 : 1;
                    }
                }
            }
        }

        // On parcourt toutes nos donnees pour y faire une moyenne (si possible!)
        foreach ($donnees as $key => $donnee) {
            if ($donnees[$key][1] !== 0) {
                $donnees[$key] = round($donnees[$key][0] / $donnees[$key][1]);
            } else {
                $donnees[$key] = $donnees[$key][0];
            }
        }

        // On tri les entêtes (et sans altérer les clés, on en a besoin pour mettre les bonnes valeurs aux bons endroits !)
        // Tri des lignes : si par date, alors on tri en fonction des clés
        if (preg_match("/^date-.*/", $formData['croiseLigne'])) {
            ksort($lignes);
        // Sinon c'est par les labels
        } else {
            uasort($lignes, function ($a, $b) {
                return $a[0] <=> $b[0];
            });
        }
        // Tri des colonnes : Si par date, alors on tri en fonction des clés
        if (preg_match("/^date-.*/", $formData['croiseColonne'])) {
            ksort($colonnes);
        // Sinon c'est par les labels
        } else {
            uasort($colonnes, function ($a, $b) {
                return $a[0] <=> $b[0];
            });
        }

        // Si nous n'avons pas demandé un export, nous renvoyons la vue normale
        if ($exportType === self::NO_EXPORT) {
            // On génère la vue
            return $this->render('meteo/statistiques/interventions-avancees/tableau-dynamique-croise.html.twig', [
                'formType' => new InterventionsAvanceesType(),
                'formData' => $formData,
                'lignes' => $lignes,
                'colonnes' => $colonnes,
                'donnees' => $donnees,
            ]);

        // Si export PDF, alors on génère le fichier pdf
        } elseif ($exportType === self::EXPORT_PDF) {
            // On génère la vue
            $html = $this->renderView('meteo/statistiques/interventions-avancees/tableau-dynamique-croise.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
//                'baseAssets' => '',
                'formType' => new InterventionsAvanceesType(),
                'formData' => $formData,
                'lignes' => $lignes,
                'colonnes' => $colonnes,
                'nbrTableaux' => 0,
                'donnees' => $donnees,
            ]);

            // On retourne notre pdf
            return new PdfResponse(
                $this->pdfService->getOutputFromHtml($html, ['orientation' => 'Landscape']),
                "export_{$formData['periodeDebut']}_{$formData['periodeFin']}.pdf"
            );

        // Si export XLSX, alors on génère le fichier xlsx
        } elseif ($exportType === self::EXPORT_XLSX) {
            // Si au niveau colonnes ou lignes, nous avons date-mois ou date-trimestres
            $estColonnesMoisOuTrimestres = $formData['croiseColonne'] === 'date-mois' || $formData['croiseColonne'] === 'date-trimestres';
            $estLignesMoisOuTrimestres = $formData['croiseLigne'] === 'date-mois' || $formData['croiseLigne'] === 'date-trimestres';

            // Construit le fichier xlsx
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Gesip')
                ->setTitle("Statistiques avancées - Tableau dynamique croisé")
                ->setSubject("Du 01/01/{$formData['periodeDebut']} au 31/12/{$formData['periodeFin']}")
                ->setDescription("Statistiques avancées - Tableau dynamique croisé");

            // Défini des styles utilisés dans le fichier excel
            $header1 = [
                'font' => [ 'bold'  => true, 'size'  => 12, 'color' => ['argb' => '0000CC'] ],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];
            $header2 = [
                'font' => [ 'bold' => true, 'size' => 11 ],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];
            $header3 = [
                'font' => [ 'bold' => false, 'size' => 11 ],
                'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
            ];

            // On déclare la première feuille dans le fichier
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->setTitle("Du 01-01-{$formData['periodeDebut']} au 31-12-{$formData['periodeFin']}");

            // 1ère et 2ème ligne (si date-mois ou date-trimestres)
            $offsetY = 2;
            $activeSheet->getRowDimension(1)->setRowHeight(25);
            $activeSheet->getCell('A1')->setValue(
                InterventionsAvanceesType::getChoix1($formData['croiseLigne']) . ' / ' . InterventionsAvanceesType::getChoix1($formData['croiseColonne'])
            )->getStyle()->applyFromArray($header1);
            if ($estColonnesMoisOuTrimestres) {
                $offsetY = 3;
                $activeSheet->getRowDimension(1)->setRowHeight(25);
                $activeSheet->getRowDimension(2)->setRowHeight(25);
                $activeSheet->mergeCellsByColumnAndRow(1, 1, 1, 2);
            }
            $offsetX = 2;
            foreach ($colonnes as $y => $colonne) {
                if ($estColonnesMoisOuTrimestres) {
                    if (str_ends_with($y, '-')) {
                        $activeSheet->getCellByColumnAndRow($offsetX, 1)->setValue($colonne)->getStyle()->applyFromArray($header1);
                        $activeSheet->mergeCellsByColumnAndRow($offsetX, 1, $offsetX + ($formData['croiseColonne'] === 'date-mois' ? 11 : 3), 1);
                    } else {
                        $activeSheet->getCellByColumnAndRow($offsetX, 2)->setValue($colonne)->getStyle()->applyFromArray($header1);
                        $offsetX++;
                    }
                } else {
                    $activeSheet->getCellByColumnAndRow($offsetX, 1)->setValue($colonne)->getStyle()->applyFromArray($header1);
                    $offsetX++;
                }
            }

            // 2ème ou 3ème ligne (les données !)
            foreach ($lignes as $x => $ligne) {
                $activeSheet->getCellByColumnAndRow(1, $offsetY)->setValue($ligne)->getStyle()->applyFromArray(
                    (!$estLignesMoisOuTrimestres || str_ends_with($x, '-') ? $header2 : $header3)
                );
                $offsetX = 2;
                foreach ($colonnes as $y => $colonne) {
                    if (!$estColonnesMoisOuTrimestres || !str_ends_with($y, '-')) {
                        $key = $x . '|' . $y;
                        if (isset($donnees[$key])) {
                            $activeSheet->getCellByColumnAndRow($offsetX, $offsetY)->setValue($donnees[$key])->getStyle()->applyFromArray($header3);
                        } else {
                            $activeSheet->getCellByColumnAndRow($offsetX, $offsetY)->setValue(0)->getStyle()->applyFromArray($header3);
                        }
                        $offsetX++;
                    }
                }
                $offsetY++;
            }

            // On calcule automatiquement les dimensions des cellules en fonction du contenu
            for ($ascii = ord('A'); $ascii <= ord($activeSheet->getHighestColumn()); $ascii++) {
                $activeSheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            }

            // Ouvre un flux pour envoyer la réponse
            $writer = new Xlsx($spreadsheet);
            $response = new StreamedResponse(function () use ($writer) {
                $writer->save('php://output');
            });

            // Défini les headers qui vont bien
            $response->headers->set('Content-Type', 'application/vnd.ms-excel');
            $response->headers->set('Content-Disposition', "attachment;filename=\"export_{$formData['periodeDebut']}-{$formData['periodeFin']}.xlsx\"");
            $response->headers->set('Cache-Control', 'max-age=0');

            // Envoi la réponse
            return $response;
        }
    }


    private function ajoutCroiseEntete(array &$lignesOuColonnes, $id, string $label)
    {
        if (!isset($lignesOuColonnes[$id])) {
            $lignesOuColonnes[$id] = $label;
        }
        return $id;
    }
}
