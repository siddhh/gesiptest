<?php

namespace App\Controller\Meteo\Statistiques;

use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatRefusee;
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class InterventionsController extends AbstractController
{

    /**
     * @Route("/meteo/statistiques/interventions/{mode?}/{annee?}/{export?}", name="meteo-statistiques-interventions", requirements={
     *  "mode" = "delai-pilote|delai-equipe|delai-composants|detail-pilote|detail-equipe|detail-composants|detail-bureau-rattachement|detail-esi|global|lien",
     *  "annee" = "\d{4}",
     *  "export" = "xlsx|pdf"
     * })
     */
    public function index(Request $request, Pdf $pdf, ?string $mode, ?int $annee = null, ?string $export = null): Response
    {
        // Si on récupère la période demandée (si l'année n'est pas fournie, on prend l'année courante)
        if (empty($annee)) {
            $annee = date('Y');
        } elseif ($annee > date('Y') || $annee < date('Y') - 5) {
            throw new BadRequestHttpException('Année non prise en charge.');
        }
        $periodeDebut = \DateTime::createFromFormat('Y-m-d H:i:s', $annee . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));
        $periodeFin = \DateTime::createFromFormat('Y-m-d H:i:s', $annee . '-12-31 23:59:59', new \DateTimeZone('Europe/Paris'));

        // A partir des modes et de l'année, on construit un tableau contenant les statistiques demandées
        $titre = '';
        $dataToDisplay = [];
        switch ($mode) {
            case 'delai-pilote':
                $titre = "Délai moyen de validation d’une demande par pilotes en {$annee}";
                $dataToDisplay = $this->getTableauDelai($periodeDebut, $periodeFin, 'pilote');
                break;
            case 'delai-equipe':
                $titre = "Délai moyen de validation d’une demande par équipes en {$annee}";
                $dataToDisplay = $this->getTableauDelai($periodeDebut, $periodeFin, 'equipe');
                break;
            case 'delai-composants':
                $titre = "Délai moyen de validation d’une demande par composants en {$annee}";
                $dataToDisplay = $this->getTableauDelai($periodeDebut, $periodeFin, 'composant');
                break;
            case 'detail-pilote':
                $titre = "État détaillé des demandes d’intervention validées en {$annee} par pilotes";
                $dataToDisplay = $this->getTableauDetails($periodeDebut, $periodeFin, 'pilote');
                break;
            case 'detail-equipe':
                $titre = "État détaillé des demandes d’intervention validées en {$annee} par équipes pilotes";
                $dataToDisplay = $this->getTableauDetails($periodeDebut, $periodeFin, 'equipe');
                break;
            case 'detail-composants':
                $titre = "État détaillé des demandes d’intervention validées en {$annee} par composants";
                $dataToDisplay = $this->getTableauDetails($periodeDebut, $periodeFin, 'composant');
                break;
            case 'detail-bureau-rattachement':
                $titre = "État détaillé des demandes d’intervention validées en {$annee} par Bureaux de rattachement";
                $dataToDisplay = $this->getTableauDetails($periodeDebut, $periodeFin, 'bureau-rattachement');
                break;
            case 'detail-esi':
                $titre = "État détaillé des demandes d’intervention validées en {$annee} par ESI";
                $dataToDisplay = $this->getTableauDetails($periodeDebut, $periodeFin, 'esi');
                break;
            case 'global':
                $titre = "État global des demandes d’intervention validées en {$annee}";
                $dataToDisplay = $this->getTableauEtatGlobal($periodeDebut, $periodeFin);
                break;
            case 'lien':
                $titre = "État des liens entre MEP SSI et demandes d’intervention en {$annee}";
                $dataToDisplay = $this->getTableauLien($periodeDebut, $periodeFin);
                break;
        }

        // On génère le type de réponse en fonction de ce qui est demandé à l'aide du tableau précédement généré
        switch ($export) {
            case 'xlsx':
                return $this->getExportXlsx($titre, $dataToDisplay);
            case 'pdf':
                return $this->getExportPdf($titre, $dataToDisplay, $pdf);
            default:
                return $this->render('meteo/statistiques/interventions/stats.html.twig', [
                    'dataToDisplay' => $dataToDisplay,
                    'titreEtat'     => $titre,
                    'mode'          => $mode,
                    'annee'         => $annee,
                ]);
        }
    }

    /**
     * Retourne le calcul du délai moyen de traitement d'une demande (passage de l'etat analyse en cours à accordé ou refusé)
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @param string $groupe
     * @return array
     */
    private function getTableauDelai(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin, string $groupe) :array
    {
        // Récupère la liste des décisions et des regroupements correspondant aux critères
        list($groupeObjets, $groupeDecisions) = $this->getDecisions($periodeDebut, $periodeFin, $groupe);
        // Tri le tableau des regroupement (libellés) par ordre alphabetique
        $bodyToDisplay = [];
        uasort($groupeObjets, function ($a, $b) {
            return strnatcasecmp($a['ordre'], $b['ordre']);
        });
        // Parcours le tableau des décisions par groupe pour calculer la moyenne de délai des décisions
        foreach ($groupeObjets as $groupeId => $groupeObject) {
            $decisionsCount = 0;
            $decisionsSum = 0;
            foreach ($groupeDecisions[$groupeId] as $decision) {
                $decisionsCount ++;
                $decisionsSum += $decision['duree'];
            }
            $bodyToDisplay[] = [$groupeObject['label'], (string)round($decisionsSum / $decisionsCount, 1)];
        }
        // Produit et retourne un tableau à afficher exploitable par la vue
        $colonneHeads = [
            'pilote'    => 'Pilotes',
            'equipe'    => 'Equipes',
            'composant' => 'Composants',
        ];
        $dataToDisplay = [
            'columnCellTypes' => ['th', 'td'],
            'head' => [
                $colonneHeads[$groupe],
                'Délai moyen en jour',
            ],
            'body'  => $bodyToDisplay,
        ];
        return $dataToDisplay;
    }


    /**
     * Retourne le calcul du délai moyen de traitement d'une demande (passage de l'etat analyse en cours à accordé ou refusé)
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @param string $groupe
     * @return array
     */
    private function getTableauDetails(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin, string $groupe) :array
    {
        // Récupère la liste des décisions et des regroupements correspondant aux critères
        list($groupeObjets, $groupeDecisions) = $this->getDecisions($periodeDebut, $periodeFin, $groupe);
        // Tri le tableau des regroupement (libellés) par ordre alphabetique
        $bodyToDisplay = [];
        uasort($groupeObjets, function ($a, $b) {
            return strnatcasecmp($a['ordre'], $b['ordre']);
        });
        // Parcours le tableau des décisions par groupe pour procéder aux dénombrements
        $total = 0;
        foreach ($groupeObjets as $groupeId => $groupeObject) {
            $nb = count($groupeDecisions[$groupeId]);
            $bodyToDisplay[] = [$groupeObject['label'], $nb];
            $total += $nb;
        }
        // Produit et retourne un tableau à afficher exploitable par la vue
        $colonneHeads = [
            'pilote'                => 'Pilotes',
            'equipe'                => 'Equipes',
            'composant'             => 'Composants',
            'bureau-rattachement'   => 'Bureau de rattachement',
            'esi'                   => 'Esi',
        ];
        $nonAccocieItem = array_search('Non associé', array_column($bodyToDisplay, 0));
        if ($nonAccocieItem !== false) {
            $bodyToDisplay[] = [ 'Non associé', $bodyToDisplay[$nonAccocieItem][1]];
            unset($bodyToDisplay[$nonAccocieItem]);
        }
        $dataToDisplay = [
            'columnCellTypes' => ['th', 'td'],
            'head' => [
                $colonneHeads[$groupe],
                'Nombre de demandes',
            ],
            'body'  => $bodyToDisplay,
            'foot' => [
                'Total', $total
            ]
        ];

        return $dataToDisplay;
    }

    /**
     * Retourne le dénombrement des interventions par mois pour une période donnée
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @return array
     */
    private function getTableauEtatGlobal(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin) :array
    {
        $demandes = $this->getDoctrine()->getRepository(DemandeIntervention::class)
            ->listeDemandesInterventionsParHistorique(
                $periodeDebut,
                $periodeFin,
                [
                    EtatAccordee::class,
                    EtatRefusee::class,
                ]
            );
        $anneeSomme = 0;
        $moisLabels = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        $moisSommes = array_fill(0, 12, 0);
        foreach ($demandes as $demande) {
            foreach ($demande->getHistoriqueStatus() as $historiqueStatus) {
                if (in_array($historiqueStatus->getStatus(), [EtatAccordee::class, EtatRefusee::class])) {
                    $mois = (int)$historiqueStatus->getAjouteLe()->format('m') - 1;
                    $moisSommes[$mois]++;
                    $anneeSomme++;
                    break;
                }
            }
        }
        $dataToDisplay = [
            'columnCellTypes' => ['th', 'td'],
            'head' => [
                'Mois',
                'Nombre de demandes'
            ],
            'body' => array_map(function ($label, $somme) {
                return [$label, (string)$somme];
            }, $moisLabels, $moisSommes),
            'foot' => ['Total', $anneeSomme]
        ];
        return $dataToDisplay;
    }

    /**
     * Retourne le dénombrement des meps par mois pour une période donnée
     * @param \DateTimeInterface $periodeDebut
     * @param \DateTimeInterface $periodeFin
     * @return array
     */
    private function getTableauLien(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin) :array
    {
        $meps = $this->getDoctrine()->getRepository(MepSsi::class)->listeMepsLien($periodeDebut, $periodeFin);
        $dataToDisplay = [
            'columnCellTypes' => ['th', 'td', 'td'],
            'head' => [
                'Mois',
                'Nombre de MEP SSI',
                'dont MEP SSI liées à des demandes'
            ],
            'body'  => [],
        ];
        $mois = 0;
        $anneeMepsCount = 0;
        $anneeMepsAvecInterventionsCount = 0;
        $mep = reset($meps);
        $aMois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        while ($mois < 12) {
            $moisMepsCount = 0;
            $moisMepsAvecInterventionsCount = 0;
            while ($mep !== false && (int)substr($mep['mepDate'], 5, 2) == $mois + 1) {
                $moisMepsCount++;
                if (count($mep[0]->getDemandesInterventions()) > 0) {
                    $moisMepsAvecInterventionsCount++;
                }
                $mep = next($meps);
            }
            $anneeMepsCount += $moisMepsCount;
            $anneeMepsAvecInterventionsCount += $moisMepsAvecInterventionsCount;
            $dataToDisplay['body'][] = [$aMois[$mois], (string)$moisMepsCount, (string)$moisMepsAvecInterventionsCount];
            $mois++;
        }
        $dataToDisplay['foot'] = ['Total', $anneeMepsCount, $anneeMepsAvecInterventionsCount];
        return $dataToDisplay;
    }

    /**
     * Retourne un tableau contenant les objets participants aux regroupements ainsi que les statistiques sur les décisions
     */
    private function getDecisions(\DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin, string $groupe): array
    {
        // Récupère les demandes devant participer au calcul
        $demandes = $this->getDoctrine()->getRepository(DemandeIntervention::class)
            ->listeDemandesInterventionsParHistorique(
                $periodeDebut,
                $periodeFin,
                [
                    EtatAccordee::class,
                    EtatAnalyseEnCours::class,
                    EtatRefusee::class,
                ]
            );
        // Pour chaque demande
        $groupeDecisions = [];
        $groupeObjets = [];
        foreach ($demandes as $demande) {
            // On récupère l'objet sur lequel le regroupement est effectué
            $groupeObjet = null;
            $composant = $demande->getComposant();
            switch ($groupe) {
                case 'pilote':
                    $groupeObjet = $composant->getPilote();
                    break;
                case 'equipe':
                    $groupeObjet = $composant->getEquipe();
                    break;
                case 'composant':
                    $groupeObjet = $composant;
                    break;
                case 'bureau-rattachement':
                    $groupeObjet = $composant->getBureauRattachement();
                    break;
                case 'esi':
                    $groupeObjet = $composant->getExploitant();
                    break;
            }
            // Si l'objet sur lequel le regroupement est effectué n'est pas défini, on ignore la demande
            if (!empty($groupeObjet)) {
                $groupeObjetId = $groupeObjet->getId();
            } else {
                $groupeObjetId = 0;
            }
            // Parcours l'historique de la demande à la recherche de transition Refus ou Accord => AnalyseEnCours (historique décroissant)
            $decisionStats = ['demande_id' => $demande->getId(), 'debut' => null, 'fin' => null, 'fin_etat' => null, 'duree' => null];
            foreach ($demande->getHistoriqueStatus() as $historiqueStatus) {
                $statut = $historiqueStatus->getStatus();
                if (in_array($statut, [EtatAccordee::class, EtatRefusee::class])) {
                    $decisionStats['fin'] = $historiqueStatus->getAjouteLe();
                    $decisionStats['fin_etat'] = $statut;
                } elseif (!empty($decisionStats['fin']) && in_array($statut, [EtatAnalyseEnCours::class])) {
                    $decisionStats['debut'] = $historiqueStatus->getAjouteLe();
                }
                if (null !== $decisionStats['debut'] && null !== $decisionStats['fin']) { // Les décisions non-complètes (pas encore refusée /accordée) ne seront pas prises en compte
                    $decisionStats['duree'] = ($decisionStats['fin']->getTimestamp() - $decisionStats['debut']->getTimestamp()) / 86400;
                    $decisionStats['duree'] = ($decisionStats['duree'] < 0) ? 0 : $decisionStats['duree'];

                    if ($decisionStats['fin'] >= $periodeDebut && $decisionStats['fin'] <= $periodeFin) {
                        if (!isset($groupeDecisions[$groupeObjetId])) {
                            $groupeDecisions[$groupeObjetId] = [];
                        }
                        $groupeDecisions[$groupeObjetId][] = $decisionStats;
                    }
                    break; // on récupère la dernière opération de validation, si il y en a d'autres pour la même demande, elles seront ignorées.
                }
            }

            // Si on découvre un nouvel objet de groupement qui comporte des stats, on le rajoute à notre dictionnaire
            if (empty($groupeObjets[$groupeObjetId]) && !empty($groupeDecisions[$groupeObjetId])) {
                if ($groupe == 'pilote') {
                    $groupeObjets[$groupeObjetId] = [
                        'label' => is_object($groupeObjet) ? $groupeObjet->getNomCompletCourt() : "Non associé",
                        'ordre' => is_object($groupeObjet) ? $groupeObjet->getNom() . ' ' . $groupeObjet->getPrenom() : "Non associé",
                    ];
                } else {
                    $groupeObjets[$groupeObjetId] = [
                        'label' => is_object($groupeObjet) ? $groupeObjet->getLabel() : "Non associé",
                        'ordre' => is_object($groupeObjet) ? $groupeObjet->getLabel() : "Non associé",
                    ];
                }
            }
        }
        return [
            $groupeObjets,
            $groupeDecisions,
        ];
    }

    /**
     * Construit et retourne un export Xlsx
     */
    private function getExportXlsx(string $titre, array $dataToDisplay): StreamedResponse
    {
        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle($titre);

        // Défini des styles utilisés dans le fichier excel
        $header1 = [    // Titre
            'font' => [ 'bold'  => true, 'size'  => 13, 'color' => ['argb' => '0000CC']],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $header2 = [    // Entete
            'font' => [ 'bold'  => true, 'size'  => 12, 'color' => ['argb' => '0000CC']],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $header3 = [    // Corps
            'font' => [ 'bold'  => false, 'size'  => 11 ],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $header4 = [    // Totaux / Colonne libellés
            'font' => [ 'bold'  => true, 'size'  => 11 ],
            'alignment' => [ 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true ]
        ];
        $activeWorksheet = $spreadsheet->getActiveSheet()->setTitle((new \DateTime())->format('Y-m-d'));

        // Ajoute le titre du composant représentant le label du composant
        $nombreColonnes = count($dataToDisplay['head']);
        $activeWorksheet->getCell('A1')
            ->setValue($titre)
            ->getStyle()->applyFromArray($header1);
        $activeWorksheet->getRowDimension(1)->setRowHeight(35);
        $activeWorksheet->mergeCellsByColumnAndRow(1, 1, $nombreColonnes, 1);

        // On ajoute les colonnes
        $derniereColonne = chr(ord('A') + $nombreColonnes - 1);
        $enteteLigne = 2;
        $corpsDerniereLigne = count($dataToDisplay['body']) + $enteteLigne;
        $activeWorksheet->fromArray($dataToDisplay['head'], null, 'A2')
            ->getStyle("A2:{$derniereColonne}2")
            ->applyFromArray($header2);
        $activeWorksheet->getRowDimension(2)->setRowHeight(25);

        // On ajoute les données
        $corpsPremiereLigne = $enteteLigne + 1;
        $activeWorksheet->fromArray($dataToDisplay['body'], null, "A{$corpsPremiereLigne}")
            ->getStyle("A{$corpsPremiereLigne}:{$derniereColonne}{$corpsDerniereLigne}")
            ->applyFromArray($header3);

        // On ajoute le pied de tableau
        $piedLigne = $corpsDerniereLigne;
        if (!empty($dataToDisplay['foot'])) {
            $piedLigne = $corpsDerniereLigne + 1;
            $activeWorksheet->fromArray($dataToDisplay['foot'], null, "A{$piedLigne}")
                ->getStyle("A{$piedLigne}:{$derniereColonne}{$piedLigne}")
                ->applyFromArray($header4);
        }

        // On modifie le style des colonnes si nécessaire
        foreach ($dataToDisplay['columnCellTypes'] as $index => $cellType) {
            if ($cellType == 'th') {
                $nomColonne =  chr(ord('A') + $index);
                $activeWorksheet->getStyle("{$nomColonne}{$corpsPremiereLigne}:{$nomColonne}{$piedLigne}")
                ->applyFromArray($header4);
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

        // Défini les headers
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"export.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // Envoi la réponse
        return $response;
    }

    /**
     * Construit et retourne un export Pdf
     */
    private function getExportPdf(string $titre, array $dataToDisplay, Pdf $pdf): PdfResponse
    {
        // On génère la vue html
        $html = $this->renderView('meteo/statistiques/interventions/stats.pdf.html.twig', [
            'baseAssets'    => $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public',
            'dataToDisplay' => $dataToDisplay,
            'titreEtat'     => $titre,
        ]);

        // On crée notre fichier pdf associé au html généré précédemment, que l'on renvoi au navigateur
        return new PdfResponse(
            $pdf->getOutputFromHtml($html, [
                'orientation' => 'Landscape',
                'default-header' => true
            ]),
            'export.pdf'
        );
    }
}
