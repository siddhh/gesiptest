<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\References\Usager;
use App\Form\References\UsagerType;
use App\Entity\References\TypeElement;
use App\Form\References\TypeElementType;
use App\Entity\References\MotifRenvoi;
use App\Form\References\MotifRenvoiType;
use App\Entity\References\ListeDiffusionSi2a;
use App\Form\References\ListeDiffusionSi2aType;
use App\Form\References\MotifInterventionType;
use App\Entity\References\MotifIntervention;
use App\Form\References\ProfilType;
use App\Entity\References\Profil;
use App\Form\References\ImpactMeteoType;
use App\Entity\References\ImpactMeteo;
use App\Form\References\DomaineType;
use App\Entity\References\Domaine;
use App\Form\References\NatureImpactType;
use App\Entity\References\NatureImpact;
use App\Form\References\MotifRefusType;
use App\Entity\References\MotifRefus;
use App\Form\References\MissionType;
use App\Entity\References\Mission;
use App\Form\References\GridMepType;
use App\Entity\References\GridMep;
use App\Form\References\StatutMepType;
use App\Entity\References\StatutMep;

class ReferenceController extends AbstractController
{

    /**
     * @Route("/gestion/reference/type_elements", name="gestion-reference-type_elements")
     */
    public function montreGestionTypeElements(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(TypeElementType::class);
        // récupère la liste des motifs de renvoi
        $referenceList = $this->getDoctrine()->getRepository(TypeElement::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => TypeElement::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Types d\'élements'
        ]);
    }

    /**
     * @Route("/gestion/reference/motifs_renvoi", name="gestion-reference-motifs_renvoi")
     */
    public function montreGestionMotifsDeRenvoi(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(MotifRenvoiType::class);
        // récupère la liste des motifs de renvoi
        $referenceList = $this->getDoctrine()->getRepository(MotifRenvoi::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => MotifRenvoi::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Motifs de renvoi'
        ]);
    }

    /**
     * @Route("/gestion/reference/motifs_intervention", name="gestion-reference-motifs_intervention")
     */
    public function montreGestionMotifsIntervention(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(MotifInterventionType::class);
        // récupère la liste des motifs d'intervention'
        $referenceList = $this->getDoctrine()->getRepository(MotifIntervention::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => MotifIntervention::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Motifs d\'intervention'
        ]);
    }

    /**
     * @Route("/gestion/reference/usagers", name="gestion-reference-usagers")
     */
    public function montreGestionUsagers(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(UsagerType::class);
        // récupère la liste des motifs de renvoi
        $referenceList = $this->getDoctrine()->getRepository(Usager::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => Usager::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Usagers'
        ]);
    }

    /**
     * @Route("/gestion/reference/liste_diffusion_si2a", name="gestion-reference-liste_diffusion_si2a")
     */
    public function montreGestionListeDiffusionSi2a(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(ListeDiffusionSi2aType::class);
        // récupère la liste des motifs de renvoi
        $referenceList = $this->getDoctrine()->getRepository(ListeDiffusionSi2a::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => ListeDiffusionSi2a::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Liste de diffusion SI2'
        ]);
    }

        /**
     * @Route("/gestion/reference/profils", name="gestion-reference-profils")
     */
    public function montreGestionProfils(): Response
    {
        // récupère le formulaire correspondant à ce profil
        $form = $this->createForm(ProfilType::class);
        // récupère la liste des profils
        $referenceList = $this->getDoctrine()->getRepository(Profil::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => Profil::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Profils'
        ]);
    }

    /**
     * @Route("/gestion/reference/impacts_meteo", name="gestion-reference-impacts_meteo")
     */
    public function montreGestionImpactsMeteo(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(ImpactMeteoType::class);
        // récupère la liste des motifs de renvoi
        $referenceList = $this->getDoctrine()->getRepository(ImpactMeteo::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => ImpactMeteo::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Impacts météo'
        ]);
    }

    /**
     * @Route("/gestion/reference/domaines", name="gestion-reference-domaines")
     */
    public function montreGestionDomaines(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(DomaineType::class);
        // récupère la liste des motifs de renvoi
        $referenceList = $this->getDoctrine()->getRepository(Domaine::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => Domaine::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Domaines'
        ]);
    }

    /**
     * @Route("/gestion/reference/natures_impact", name="gestion-reference-natures_impact")
     */
    public function montreGestionNaturesImpact(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(NatureImpactType::class);
        // récupère la liste des natures d'impact
        $referenceList = $this->getDoctrine()->getRepository(NatureImpact::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => NatureImpact::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Natures de l\'impact'
        ]);
    }

    /**
     * @Route("/gestion/reference/motifs_refus", name="gestion-reference-motifs_refus")
     */
    public function montreGestionMotifsDeRefus(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(MotifRefusType::class);
        // récupère la liste des motifs de refus
        $referenceList = $this->getDoctrine()->getRepository(MotifRefus::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => MotifRefus::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Motifs de refus'
        ]);
    }

    /**
     * @Route("/gestion/reference/missions", name="gestion-reference-missions")
     */
    public function montreGestionMission(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(MissionType::class);
        // récupère la liste des missions
        $referenceList = $this->getDoctrine()->getRepository(Mission::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => Mission::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Missions'
        ]);
    }

    /**
     * @Route("/gestion/reference/grid_meps", name="gestion-reference-grid_meps")
     */
    public function montreGestionGridMep(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(GridMepType::class);
        // récupère la liste des Grid mep
        $referenceList = $this->getDoctrine()->getRepository(GridMep::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => GridMep::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Grid mep'
        ]);
    }

    /**
     * @Route("/gestion/reference/statut_meps", name="gestion-reference-statut_meps")
     */
    public function montreGestionStatutMep(): Response
    {
        // récupère le formulaire correspondant à ce type de référence
        $form = $this->createForm(StatutMepType::class);
        // récupère la liste des Statuts mep
        $referenceList = $this->getDoctrine()->getRepository(StatutMep::class)->liste();
        // affiche la page de gestion des références
        return $this->render('gestion/references/gestion.html.twig', [
            'form'              => $form->createView(),
            'referenceClass'    => StatutMep::class,
            'referenceList'     => $referenceList,
            'titreReference'    => 'Statut mep'
        ]);
    }
}
