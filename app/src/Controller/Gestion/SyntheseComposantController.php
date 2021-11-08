<?php

namespace App\Controller\Gestion;

use App\Entity\Composant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;

class SyntheseComposantController extends AbstractController
{
    /**
     * @Route("/gestion/synthese-composant/{id<\d+>?}", name="gestion-synthese-composant")
     */
    public function editionSyntheseComposant(?int $id = null, Request $request): Response
    {
        // Récupération des données
        $listeDesComposants = $this->getDoctrine()->getRepository(Composant::class)->findBy(['archiveLe' => null], ['label' => 'asc']);
        ( $id === null ) ? $composant = null : $composant = $this->getDoctrine()->getRepository(Composant::class)->restitutionComposant($id);

        // Si le composant est archivé
        if ($composant && $composant->getArchiveLe() !== null) {
            $composant = null;
        }

        return $this->render('gestion/synthese-composant.html.twig', [
            'composantSelectionne'  => $composant,
            'composants'    => $listeDesComposants
        ]);
    }

    /**
     * @Route("/gestion/synthese-composant/{id<\d+>}/pdf", name="gestion-synthese-composant-pdf")
     */
    public function exportPdf(int $id, Request $request, Pdf $pdf): PdfResponse
    {
        // Récupération des données
        $listeDesComposants = $this->getDoctrine()->getRepository(Composant::class)->findBy(['archiveLe' => null], ['label' => 'asc']);
        $composant = $this->getDoctrine()->getRepository(Composant::class)->restitutionComposant($id);
        if (empty($composant) || $composant->getArchiveLe() !== null) {
            throw new NotFoundHttpException('Composant introuvable ou archivé.');
        }
        $nomComposant = $composant->getLabel();

        // On génère une vue avec les résultats
        $html = $this->renderView('gestion/synthese-composant.pdf.html.twig', [
            'baseAssets'    => $this->getParameter('kernel.project_dir') . '/public',
            'composantSelectionne'  => $composant,
            'composants'    => $listeDesComposants
        ]);

        // On génère et renvoie un binaire Pdf au format paysage à partir du code html généré précédement
        return new PdfResponse(
            $pdf->getOutputFromHtml($html, ['orientation' => 'Portrait']),
            "etat-synthese-composant_{$nomComposant}.pdf"
        );
    }
}
