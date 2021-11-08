<?php

namespace App\Controller\Calendrier;

use App\Entity\MepSsi;
use App\Entity\Pilote;
use App\Form\Calendrier\MepSsiType;
use App\Utils\I18nDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\StatutMep;

class MepSsiController extends AbstractController
{
    /**
     * @Route("/calendrier/mep-ssi/{mepSsi}", name="calendrier-mepssi-consulter", requirements={"mepSsi"="\d+"})
     */
    public function consulter(MepSsi $mepSsi): Response
    {
        return $this->render('calendrier/mepssi/consultation.html.twig', [ 'mepSsi' => $mepSsi ]);
    }

    /**
     * @Route("/calendrier/mep-ssi/liste/{date?}", name="calendrier-mepssi-liste", requirements={"date"="\d{4}-\d{2}"})
     */
    public function lister(?string $date = null): Response
    {
        // On initialise notre page en récupérant quelques éléments
        $em = $this->getDoctrine()->getManager();
        $pilotes = $em->getRepository(Pilote::class)->listePilotesFiltre()->getResult();

        // On récupère la date sélectionnée ou celle du jour si celle-ci est nulle.
        $dateSelectionnee = $date === null ? (new \DateTime('first day of this month')) : \DateTime::createFromFormat('Y-m-d', $date . '-01');
        $dateSelectionnee->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('Europe/Paris'));

        // On génère le système pour la navigation temporelle de la page
        $navigationTemporelle = [];
        for ($iperiode = -5; $iperiode <= 5; $iperiode++) {
            if ($iperiode < 0) {
                $calculDate = (clone $dateSelectionnee)->sub(new \DateInterval('P' . abs($iperiode) . 'M'));
            } elseif ($iperiode == 0) {
                $calculDate = $dateSelectionnee;
            } elseif ($iperiode > 0) {
                $calculDate = (clone $dateSelectionnee)->add(new \DateInterval('P' . abs($iperiode) . 'M'));
            }

            $navigationTemporelle[] = [
                'label' => $iperiode == 0 ? I18nDate::__('F Y', $calculDate) : I18nDate::__('F', $calculDate),
                'date' => $calculDate->format('Y-m'),
            ];
        }

        // On va récupérer toutes les meps ssi ayant eu lieu au cours du mois sélectionné
        $listeMepSsi = $em->getRepository(MepSsi::class)->listeParMois($dateSelectionnee);

        // On renvoi la vue
        return $this->render('calendrier/mepssi/liste.html.twig', [
            'pilotes' => $pilotes,
            'navigation' => $navigationTemporelle,
            'listeMepSsi' => $listeMepSsi,
        ]);
    }

    /**
     * @Route("/calendrier/mep-ssi/liste/export/xlsx", name="calendrier-mepssi-liste-defaut-xlsx")
     * @Route("/calendrier/mep-ssi/liste/{date?}/export/xlsx", name="calendrier-mepssi-liste-xlsx",
     *                                                         requirements={"date"="\d{4}-\d{2}"})
     */
    public function exportXlsx(?string $date = null): Response
    {
        // On initialise notre export en récupérant l'entity manager
        $em = $this->getDoctrine()->getManager();

        // La timezone utilisée est Paris
        $dtz = new \DateTimeZone('Europe/Paris');

        // On récupère la date sélectionnée ou celle du jour si celle-ci est nulle.
        $dateSelectionnee = $date === null ? (new \DateTime('first day of this month')) : \DateTime::createFromFormat('Y-m-d', $date . '-01');
        $dateSelectionnee->setTime(0, 0, 0)->setTimezone($dtz);
        $dateStrFr = I18nDate::__('F Y', $dateSelectionnee);

        // On va récupérer toutes les meps ssi ayant eu lieu au cours du mois sélectionné
        $listeMepSsi = $em->getRepository(MepSsi::class)->listeParMois($dateSelectionnee);

        // Construit le fichier xlsx
        $colonnes = [
            'Statut', 'Début', 'Fin', 'Mise en service', 'LEP', 'Composants', 'Palier', 'Équipe', 'Pilotes',
            'Description', 'Impacts', 'Risques'
        ];
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle("Calendrier des MEP SSI");

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
        $activeWorksheet = $spreadsheet->getActiveSheet()->setTitle($dateStrFr);

        // Ajoute le titre du composant représentant le label du composant
        $activeWorksheet->getCell('A1')
            ->setValue("Calendrier des MEP SSI - $dateStrFr")
            ->getStyle()->applyFromArray($header1);
        $activeWorksheet->getRowDimension(1)->setRowHeight(35);
        $activeWorksheet->mergeCellsByColumnAndRow(1, 1, count($colonnes), 1);

        // On ajoute les colonnes
        $derniereColonne = chr(ord('A') + count($colonnes) - 1);
        $derniereLigne = count($listeMepSsi) + 2;
        $activeWorksheet->fromArray($colonnes, null, 'A2')
            ->getStyle("A2:{$derniereColonne}2")
            ->applyFromArray($header2);
        $activeWorksheet->getRowDimension(2)->setRowHeight(25);

        // On parcourt les données pour les mettre en forme
        $donnees = [];
        /** @var MepSsi $mepssi */
        foreach ($listeMepSsi as $mepssi) {
            // On met en forme les composants
            $composants = '';
            foreach ($mepssi->getComposants() as $composant) {
                $composants .= ', ' . $composant->getLabel();
            }
            $composants = substr($composants, 2);

            // On met en forme les pilotes
            $pilotes = '';
            foreach ($mepssi->getPilotes() as $pilote) {
                $pilotes .= ', ' . $pilote->getNomCompletCourt();
            }
            $pilotes = substr($pilotes, 2);

            // On met en forme les autres données
            $donnees[] = [
                $mepssi->getStatutLabel(), // Statut
                $mepssi->getMepDebut() ? $mepssi->getMepDebut()->setTimezone($dtz)->format('d/m/Y') : '',   // Début
                $mepssi->getMepFin() ? $mepssi->getMepFin()->setTimezone($dtz)->format('d/m/Y') : '',       // Fin
                $mepssi->getMes() ? $mepssi->getMes()->setTimezone($dtz)->format('d/m/Y') : '',             // Mise en service
                $mepssi->getLep() ? $mepssi->getLep()->setTimezone($dtz)->format('d/m/Y') : '',             // LEP
                $composants, // Composants
                $mepssi->getPalier(), // Palier
                $mepssi->getEquipe()->getLabel(), // Équipe
                $pilotes, // Pilotes
                $mepssi->getDescription(), // Description
                $mepssi->getImpacts(), // Impacts
                $mepssi->getRisques(), // Risques
            ];
        }

        // On ajoute les données
        $activeWorksheet->fromArray($donnees, null, 'A3')
            ->getStyle("A3:{$derniereColonne}{$derniereLigne}")
            ->applyFromArray($header3);

        // On redimensionne toutes les cellules automatiquement
        for ($ascii = ord('A'); $ascii <= ord($derniereColonne); $ascii++) {
            if ($ascii < ord('J')) {
                $activeWorksheet->getColumnDimension(chr($ascii))->setAutoSize(true);
            } else {
                $activeWorksheet->getColumnDimension(chr($ascii))->setWidth(50);
            }
        }

        // Ouvre un flux pour envoyer la réponse
        $writer = new Xlsx($spreadsheet);
        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        // Défini les headers
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment;filename=\"export-mepssi-{$dateSelectionnee->format('m-Y')}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        // On envoi la réponse
        return $response;
    }

    /**
     * @Route("/calendrier/mep-ssi/archiver/{mepSsi}", name="calendrier-mepssi-archiver")
     */
    public function archiver(Request $request, MepSsi $mepSsi)
    {
        // On met à jour la MEP SSI
        $em = $this->getDoctrine()->getManager();
        $mepSsi->setStatut($em->getRepository(StatutMep::class)->findOneBy(['label' => 'ARCHIVE']));
        $em->flush();
        // On ajoute un message flash de confirmation
        $this->addFlash("success", "La MEP SSI a bien été archivée.");
        // On redirige sur la page précédente ou la route "calendrier-mepssi-liste" (si pas de referer)
        $redirectionUrl = $request->headers->get('referer') ? $request->headers->get('referer') : $this->generateUrl('calendrier-mepssi-liste');
        return $this->redirect($redirectionUrl);
    }

    /**
     * @Route("/calendrier/mep-ssi/creer/{mepSsi?}", name="calendrier-mepssi-creer")
     * (Nous pré-remplissons une nouvelle mepSsi avec les valeurs de celle passée dans la route (si existante))
     */
    public function creer(Request $request, ?MepSsi $mepSsi): Response
    {
        // On crée notre nouvelle entité ainsi que notre formulaire
        $mepSsi = $mepSsi === null ? new MepSsi() : clone $mepSsi;
        $form = $this->createForm(MepSsiType::class, $mepSsi);
        $form->handleRequest($request);

        // Si le formulaire a été soumis et est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // On enregistre l'entité
            $em = $this->getDoctrine()->getManager();
            $mepSsi->setDemandePar($this->getUser());
            $em->persist($mepSsi);
            $em->flush();
            // On redirige avec un message flash de succès.
            $this->addFlash("success", "La MEP SSI a été créée avec succès.");
            return $this->redirectToRoute('calendrier-mepssi-liste');
        }

        // On affiche la vue
        return $this->render('calendrier/mepssi/formulaire.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Ajout d\'une MEP SSI'
        ]);
    }

    /**
     * @Route("/calendrier/mep-ssi/modifier/{mepSsi}", name="calendrier-mepssi-modifier")
     */
    public function modifier(Request $request, MepSsi $mepSsi): Response
    {
        // On crée notre nouvelle entité ainsi que notre formulaire
        $form = $this->createForm(MepSsiType::class, $mepSsi);
        $form->handleRequest($request);

        // Si le formulaire a été soumis et est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // On enregistre l'entité
            $em = $this->getDoctrine()->getManager();
            $em->persist($mepSsi);
            $em->flush();
            // On redirige avec un message flash de succès.
            $this->addFlash("success", "La MEP SSI a été modifiée avec succès.");
            return $this->redirectToRoute('calendrier-mepssi-liste');
        }

        // On affiche la vue
        return $this->render('calendrier/mepssi/formulaire.html.twig', [
            'form'  => $form->createView(),
            'titre' => 'Modification de la MEP SSI'
        ]);
    }
}
