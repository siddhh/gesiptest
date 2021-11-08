<?php

namespace App\Controller;

use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Entity\Pilote;
use App\Entity\References\Domaine;
use App\Entity\References\Mission;
use App\Entity\Service;
use App\Form\RechercheBalfServiceType;
use App\Form\RechercheRestitutionComposantType;
use App\Repository\Composant\AnnuaireRepository;
use App\Repository\ComposantRepository;
use App\Repository\DemandeInterventionRepository;
use App\Utils\ChaineDeCaracteres;
use App\Utils\Pagination;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatSaisirRealise;
use Doctrine\ORM\Query\Expr\Join;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RestitutionController extends AbstractController
{
    const EXPORT_XLSX = 'xlsx';
    const EXPORT_PDF = 'pdf';

    /**
     * @Route("/restitution/{type}/{export?}", name="restitutions-listing", requirements={
     *     "type"="composants|domaines|pilotes|equipes|services|esi|bureaux-rattachement|missions",
     *     "export"="xlsx|pdf"
     * })
     * @param Request     $request
     * @param string      $type
     * @param string|null $export
     * @param Pdf         $pdf
     *
     * @return Response
     */
    public function listingsRestitutions(Request $request, string $type, ?string $export, Pdf $pdf): Response
    {
        // On déclare quelques variables
        $em = $this->getDoctrine()->getManager();
        $donnees = [];

        // On va chercher les informations en fonction du type demandé
        switch ($type) {
            // ===== Type "Composants" ====
            case 'composants':
                return $this->listingsRestitutionsComposants($request, $pdf);
                break;

            // ===== Type "ESI" =====
            case 'esi':
                $donnees = $em->getRepository(Service::class)->restitutionListingEsi();
                break;

            // ===== Type "Domaines" =====
            case 'domaines':
                $donnees = $em->getRepository(Domaine::class)->restitutionListing();
                break;

            // ===== Type "Pilotes" =====
            case 'pilotes':
                $donnees = $em->getRepository(Pilote::class)->restitutionListing();
                break;

            // ===== Type "Équipes" =====
            case 'equipes':
                $donnees = $em->getRepository(Service::class)->restitutionListingEquipesPilotage();
                break;

            // ===== Type "Bureaux de rattachement" =====
            case 'bureaux-rattachement':
                $donnees = $em->getRepository(Service::class)->restitutionListingBureauxRattachement();
                break;

            // ===== Type "Missions" =====
            case 'missions':
                $donnees = $em->getRepository(Mission::class)->restitutionListing();
                break;

            // ===== Type "Services" =====
            case 'services':
                $donnees = $em->getRepository(Service::class)->restitutionListingServices();
                break;
        }

        // Si nous avons demandé un export en XLSX
        if ($export === self::EXPORT_XLSX) {
            // On défini notre fichier en fonction du type
            switch ($type) {
                // Si le type est bizarre, on provoque une erreur.
                default:
                    throw new NotFoundHttpException();

                // ===== Type "ESI" =====
                case 'esi':
                    $titre = '%s ESI(s)';
                    $colonnes = ['ESI', 'Nombre de composants'];
                    break;
                // ===== Type "Domaines" =====
                case 'domaines':
                    $titre = '%s Domaine(s)';
                    $colonnes = ['Domaines', 'Nombre de composants'];
                    break;
                // ===== Type "Pilotes" =====
                case 'pilotes':
                    $titre = '%s Pilote(s)';
                    $colonnes = ['Pilotes', 'Nombre de composants'];
                    break;
                // ===== Type "Équipes" =====
                case 'equipes':
                    $titre = '%s Équipe(s)';
                    $colonnes = ['Équipes', 'Nombre de composants'];
                    break;
                // ===== Type "Bureaux de rattachement" =====
                case 'bureaux-rattachement':
                    $titre = '%s Bureau(x) de rattachement';
                    $colonnes = ['Bureaux de rattachement', 'Nombre de composants'];
                    break;
                // ===== Type "Missions" =====
                case 'missions':
                    $titre = '%s Mission(s)';
                    $colonnes = ['Missions', 'Nombre de composants'];
                    break;
                // ===== Type "Services" =====
                case 'services':
                    $titre = '%s Service(s)';
                    $colonnes = ['Services', 'Nombre de composants'];
                    break;
            }

            $xlsxDonnees = array_map(function ($value) {
                return [
                    $value['label'],
                    $value['nbComposants']
                ];
            }, $donnees);
            return $this->exportXlsx($titre, $colonnes, $xlsxDonnees);

        // Si nous avons demandé un export en PDF
        } elseif ($export === self::EXPORT_PDF) {
            // On génère la vue html
            $html = $this->renderView('restitution/listing/' . $type . '.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
                'type' => $type,
                'donnees' => $donnees
            ]);

            // On crée notre fichier pdf associé au html généré précédemment, que l'on renvoi au navigateur
            return new PdfResponse($pdf->getOutputFromHtml($html), 'export.pdf');
        }

        // Sinon, on rend la vue normale
        return $this->render('restitution/listing/' . $type . '.html.twig', [
            'type' => $type,
            'donnees' => $donnees
        ]);
    }

    /**
     * Controller particulier du listing de la restitution des composants
     *
     * @param Request     $request
     * @param Pdf         $pdf
     *
     * @return Response
     */
    public function listingsRestitutionsComposants(Request $request, Pdf $pdf): Response
    {
        // On récupère un formulaire
        $form = $this->createForm(RechercheRestitutionComposantType::class);

        // On commence à créer la requêt de récupération des composants
        /** @var ComposantRepository $composantsRepository */
        $composantsRepository = $this->getDoctrine()->getManager()->getRepository(Composant::class);
        $composants = $composantsRepository->createQueryBuilder('c')
            ->select(['c', 'annuaire', 'annuaire_mission', 'annuaire_service'])
            ->leftJoin('c.annuaire', 'annuaire')
            ->leftJoin('annuaire.mission', 'annuaire_mission')
            ->leftJoin('annuaire.service', 'annuaire_service')
            ->where('c.archiveLe IS NULL')
            ->orderBy('c.label', 'ASC');

        // On demande au formulaire de traiter la requête
        $form->handleRequest($request);

        // Si le formulaire est soumis et valide, on ajoute les éléments de recherche
        if ($form->isSubmitted() && $form->isValid()) {
            // Filtre "composant"
            if (!$form->get('composant')->isEmpty()) {
                $composants->andWhere('c = :composant')
                    ->setParameter('composant', $form->get('composant')->getData());
            }

            // Filtre "equipe"
            if (!$form->get('equipe')->isEmpty()) {
                $composants->andWhere('c.equipe = :equipe')
                    ->setParameter('equipe', $form->get('equipe')->getData());
            }

            // Filtre "exploitant référent"
            if (!$form->get('exploitant')->isEmpty()) {
                $composants->andWhere('c.exploitant = :exploitant')
                    ->setParameter('exploitant', $form->get('exploitant')->getData());
            }

            // Filtre "pilote"
            if (!$form->get('pilote')->isEmpty()) {
                $composants->andWhere('c.pilote = :pilote')
                    ->setParameter('pilote', $form->get('pilote')->getData());
            }

            // Filtre "exploitant système"
            if (!$form->get('exploitantSysteme')->isEmpty()) {
                // On recherche les composants associés au service donné dans le formulaire en tant "Exploitant Système"
                /** @var AnnuaireRepository $annuaireRepository */
                $annuaireRepository = $this->getDoctrine()->getManager()->getRepository(Annuaire::class);
                $annuairesExploitantSysteme = $annuaireRepository->createQueryBuilder('a')
                    ->select(['a', 'c'])
                    ->leftJoin('a.mission', 'mission')
                    ->leftJoin('a.composant', 'c')
                    ->where('a.service = :service')
                        ->setParameter('service', $form->get('exploitantSysteme')->getData())
                    ->andWhere('mission.label LIKE :missionExploitant')
                        ->setParameter('missionExploitant', '%Exploitant Système%')
                    ->getQuery()->getArrayResult();
                $annuairesExploitantSysteme = array_column($annuairesExploitantSysteme, 'composant');
                $composantsIds = array_column($annuairesExploitantSysteme, 'id');

                // On recherche par ces ids de composants là (afin d'éviter d'avoir des résultats tronquées au niveau de
                //  de l'affichage des exploitants système (par design, nous pouvons en avoir plusieurs)
                $composants
                    ->andWhere('c.id IN (:composantsId)')
                    ->setParameter('composantsId', $composantsIds);
            }

            // Filtre "plage utilisateur"
            if (!$form->get('intitulePlageUtilisateur')->isEmpty()) {
                $composants->andWhere('c.intitulePlageUtilisateur = :intitulePlageUtilisateur')
                    ->setParameter('intitulePlageUtilisateur', $form->get('intitulePlageUtilisateur')->getData());
            }

            // Filtre "usager"
            if (!$form->get('usager')->isEmpty()) {
                $composants->andWhere('c.usager = :usager')
                    ->setParameter('usager', $form->get('usager')->getData());
            }

            // Filtre "bureau de rattachement"
            if (!$form->get('bureauRattachement')->isEmpty()) {
                $composants->andWhere('c.bureauRattachement = :bureauRattachement')
                    ->setParameter('bureauRattachement', $form->get('bureauRattachement')->getData());
            }

            // Filtre "domaine"
            if (!$form->get('domaine')->isEmpty()) {
                $composants->andWhere('c.domaine = :domaine')
                    ->setParameter('domaine', $form->get('domaine')->getData());
            }
        } else {
            // Filtre "equipe" (si le champ a été pré-rempli dans le cas d'un ROLE_DME)
            if (!$form->get('equipe')->isEmpty()) {
                $composants->andWhere('c.equipe = :equipe')
                    ->setParameter('equipe', $form->get('equipe')->getData());
            }
        }

        // On récupère les données
        $donnees = $composants->getQuery()->getResult();

        // Si nous avons un export demandé
        $export = $request->query->get('export', null);

        // Et si cet export est un export XLSX
        if ($export === self::EXPORT_XLSX) {
            $titre = 'Référentiel des composants inscrits dans GESIP';
            $colonnes = [
                'Composant',
                'Exploitant référent',
                'Exploitant système',
                'Usager',
                'Domaine',
                'Pilote',
                'Équipe',
                'Plage horaire',
                'Bureau rattachement'
            ];

            $xlsxDonnees = array_map(function (Composant $composant) {
                // On parcourt l'annuaire du composant pour y récupérer les exploitants référents et systèmes
                $exploitantSysteme = [];
                foreach ($composant->getAnnuaire() as $annuaire) {
                    $label = $annuaire->getMission()->getLabel();
                    if (strpos($label, 'Exploitant Système') !== false) {
                        $exploitantSysteme[] = $annuaire->getService()->getLabel();
                    }
                }

                // On formate les données de la ligne pour le composant
                return [
                    $composant->getLabel(),
                    $composant->getExploitant() ? $composant->getExploitant()->getLabel() : null,
                    implode("\n", $exploitantSysteme),
                    $composant->getUsager()->getLabel(),
                    $composant->getDomaine() ? $composant->getDomaine()->getLabel() : null,
                    $composant->getPilote() ? $composant->getPilote()->getNomCompletCourt() : null,
                    $composant->getEquipe() ? $composant->getEquipe()->getLabel() : null,
                    $composant->getIntitulePlageUtilisateur(),
                    $composant->getBureauRattachement() ? $composant->getBureauRattachement()->getLabel() : null,
                ];
            }, $donnees);

            return $this->exportXlsx($titre, $colonnes, $xlsxDonnees);

        // Ou si cet export est un export PDF
        } elseif ($export === self::EXPORT_PDF) {
            // On génère la vue html
            $html = $this->renderView('restitution/listing/composants.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
                'composants' => $donnees
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

        // Sinon on rend la vue normale
        return $this->render('restitution/listing/composants.html.twig', [
            'form' => $form->createView(),
            'composants' => $donnees
        ]);
    }

    /**
     * @Route("/restitution/{type}/{id}", name="restitutions-fiche", requirements={
     *     "id"="\d+",
     *     "type"="composants|domaines|pilotes|equipes|services|esi|bureaux-rattachement|missions"
     * })
     */
    public function fiche(string $type, int $id): Response
    {
        // On déclare quelques variables
        $em = $this->getDoctrine()->getManager();
        $entity = null;
        $entities = [];
        $data = [];

        // On va chercher les informations en fonction du type demandé
        switch ($type) {
            // ===== Type "Composants" =====
            case 'composants':
                $entities = $em->getRepository(Composant::class)->findBy(['archiveLe' => null], ['label' => 'ASC']);
                $entity = $em->getRepository(Composant::class)->restitutionComposant($id);
                $demandes = $em->getRepository(DemandeIntervention::class)->restitutionDemandes([$id]);
                $mepssi = $em->getRepository(MepSsi::class)->restitutionMepSsi([$id]);
                $data['demandes'] = self::fusionDemandesMep($demandes, $mepssi);
                break;

            // ===== Type "ESI" =====
            case 'esi':
                $entities = $em->getRepository(Service::class)->restitutionListingEsi();
                $entity = $em->getRepository(Service::class)->find($id);
                break;

            // ===== Type "Domaines" =====
            case 'domaines':
                $entities = $em->getRepository(Domaine::class)->restitutionListing();
                $entity = $em->getRepository(Domaine::class)->find($id);
                break;

            // ===== Type "Pilotes" =====
            case 'pilotes':
                $entities = $em->getRepository(Pilote::class)->restitutionListing();
                $entity = $em->getRepository(Pilote::class)->find($id);
                break;

            // ===== Type "Équipes" =====
            case 'equipes':
                $entities = $em->getRepository(Service::class)->restitutionListingEquipesPilotage();
                $entity = $em->getRepository(Service::class)->find($id);
                break;

            // ===== Type "Bureaux de rattachement" =====
            case 'bureaux-rattachement':
                $entities = $em->getRepository(Service::class)->restitutionListingBureauxRattachement();
                $entity = $em->getRepository(Service::class)->find($id);
                break;

            // ===== Type "Missions" =====
            case 'missions':
                $entities = $em->getRepository(Mission::class)->restitutionListing();
                $entity = $em->getRepository(Mission::class)->find($id);
                $data['services'] = $em->getRepository(Composant::class)->servicesComposantsParMission($entity);
                break;

            // ===== Type "Services" =====
            case 'services':
                $entities = $em->getRepository(Service::class)->restitutionListingServices();
                $entity = $em->getRepository(Service::class)->find($id);
                $data['annuaires'] = $em->getRepository(Annuaire::class)->composantsEtMissionParService($entity);
                break;
        }

        // Si nous ne sommes pas dans les cas précédent, alors on envoie une erreur 404
        if ($entity === null) {
            throw new NotFoundHttpException();
        }

        // On rend la vue
        return $this->render('restitution/fiche/' . $type . '.html.twig', [
            'type' => $type,
            'entities' => $entities,
            'entity' => $entity,
            'data' => $data
        ]);
    }

    /**
     * @Route(
     *     "/restitution/composants/{composant}/interventions/{type}/{page?1}",
     *     name="restitutions-composant-interventions",
     *     requirements={
     *          "id"="\d+",
     *          "page"="\d+",
     *          "type"="composant|entrants|sortants"
     *      }
     * )
     */
    public function listeDemandesComposants(Composant $composant, string $type, int $page) : Response
    {
        // On crée notre requête de base
        /** @var DemandeInterventionRepository $demandesRepository */
        $demandesRepository = $this->getDoctrine()->getRepository(DemandeIntervention::class);
        $query = $demandesRepository->createQueryBuilder('d');

        // On filtre le(s) composant(s) en fonction de ce que l'on demande (entrants, sortants, ou lui-même)
        $query->andWhere('d.composant IN (:composants)');
        if ($type === "entrants") {
            $query->setParameter('composants', array_column($composant->getFluxEntrants()->toArray(), 'id'));
        } elseif ($type === "sortants") {
            $query->setParameter('composants', array_column($composant->getFluxSortants()->toArray(), 'id'));
        } else {
            $query->setParameter('composants', [$composant->getId()]);
        }

        // On filtre par status
        $query->andWhere('d.status IN (:status)')
            ->setParameter('status', [
                EtatAccordee::class,
                EtatSaisirRealise::class,
                EtatInterventionEnCours::class,
            ]);

        // On tri par date d'intervention du plus récent au plus vieux
        $query->orderBy('d.dateDebut', 'DESC');

        // On pagine
        $pagination = new Pagination($query->getQuery(), $page, true, 10);
        $response = $pagination->traitement();

        // On met en forme les données pour ne pas tout envoyer en json..
        $response['donnees'] = array_map(function (DemandeIntervention $demandeIntervention) {
            return [
                'numeroLien' => $this->generateUrl('demandes-visualisation', ['id' => $demandeIntervention->getId()]),
                'numero' => $demandeIntervention->getNumero(),
                'composantLien' => $this->generateUrl('restitutions-fiche', ['type' => 'composants', 'id' => $demandeIntervention->getComposant()->getId()]),
                'composantLabel' => $demandeIntervention->getComposant()->getLabel(),
                'etat' => $demandeIntervention->getStatusLibelle(),
                'nature' => ucfirst($demandeIntervention->getNatureIntervention()),
                'motif' => $demandeIntervention->getMotifIntervention()->getLabel(),
                'description' => $demandeIntervention->getDescription(),
                'dateDebut' => $demandeIntervention->getDateDebut()->format('d/m/Y'),
                'demandeParLien' => $this->generateUrl('restitutions-fiche', ['type' => 'services', 'id' => $demandeIntervention->getDemandePar()->getId()]),
                'demandeParLabel' => $demandeIntervention->getDemandePar()->getLabel(),
            ];
        }, $response['donnees']);

        // On envoi notre réponse
        return new JsonResponse($response);
    }

    /**
     * Fonction permettant de rendre les liens entre l'entité et le reste de la base de données.
     * @param $entity
     * @param string $type
     * @return Response
     */
    public function afficherLiens($entity, string $type) : Response
    {
        // On déclare quelques variables
        $em = $this->getDoctrine()->getManager();
        $liens = [];

        // On va chercher les informations en fonction du type demandé
        switch ($type) {
            // ===== Type "ESI" =====
            case 'esi':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'exploitantId' => $entity->getId() ]);
                $liens = [
                    $this->liensEquipes($composants),
                    $this->liensPilotes($composants),
                    $this->liensBureauxRattachement($composants),
                    $this->liensDomaines($composants),
                ];
                break;

            // ===== Type "Domaines" =====
            case 'domaines':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'domaineId' => $entity->getId() ]);
                $liens = [
                    $this->liensEquipes($composants),
                    $this->liensPilotes($composants),
                    $this->liensBureauxRattachement($composants),
                    $this->liensESI($composants),
                ];
                break;

            // ===== Type "Pilotes" =====
            case 'pilotes':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'piloteId' => $entity->getId() ]);
                $liens = [
                    $this->liensEquipes($composants),
                    $this->liensBureauxRattachement($composants),
                    $this->liensDomaines($composants),
                    $this->liensESI($composants),
                ];
                break;

            // ===== Type "Équipes" =====
            case 'equipes':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'equipeId' => $entity->getId() ]);
                $liens = [
                    $this->liensPilotes($composants),
                    $this->liensBureauxRattachement($composants),
                    $this->liensDomaines($composants),
                    $this->liensESI($composants),
                ];
                break;

            // ===== Type "Bureaux de rattachement" =====
            case 'bureaux-rattachement':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'bureauRattachementId' => $entity->getId() ]);
                $liens = [
                    $this->liensEquipes($composants),
                    $this->liensPilotes($composants),
                    $this->liensESI($composants),
                    $this->liensDomaines($composants),
                ];
                break;
        }

        // On rend la vue
        return $this->render('restitution/fiche/includes/_liens.html.twig', [ 'liens' => $liens ]);
    }

    /**
     * Fonction permettant de rendre les composants liés à l'entité passé en paramètre.
     * @param $entity
     * @param string $type
     * @return Response
     */
    public function afficherComposants($entity, string $type) : Response
    {
        // On déclare quelques variables
        $em = $this->getDoctrine()->getManager();
        $composants = [];

        // On va chercher les informations en fonction du type demandé
        switch ($type) {
            // ===== Type "ESI" =====
            case 'esi':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'exploitantId' => $entity->getId() ]);
                break;

            // ===== Type "Domaines" =====
            case 'domaines':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'domaineId' => $entity->getId() ]);
                break;

            // ===== Type "Pilotes" =====
            case 'pilotes':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'piloteTitulaireOuSuppleantId' => $entity->getId() ]);
                break;

            // ===== Type "Équipes" =====
            case 'equipes':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'equipeId' => $entity->getId() ]);
                break;

            // ===== Type "Bureaux de rattachement" =====
            case 'bureaux-rattachement':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'bureauRattachementId' => $entity->getId() ]);
                break;
        }

        // On rend la vue
        return $this->render('restitution/fiche/includes/_composants.html.twig', [
            'composants' => $composants,
            'type' => $type,
            'entityId' => $entity->getId()
        ]);
    }

    /**
     * Fonction permettant de rendre la liste des dernières demandes liées à l'entité passé en paramètre.
     * @param $entity
     * @param string $type
     * @return Response
     */
    public function afficherDemandes($entity, string $type) : Response
    {
        // On déclare quelques variables
        $em = $this->getDoctrine()->getManager();
        $demandes = [];

        // On va chercher les informations en fonction du type demandé
        switch ($type) {
            // ===== Type "ESI" =====
            case 'esi':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'exploitantId' => $entity->getId() ]);
                $demandes = $em->getRepository(DemandeIntervention::class)->restitutionDemandes($composants);
                $mepssi = $em->getRepository(MepSsi::class)->restitutionMepSsi($composants);
                $demandes = self::fusionDemandesMep($demandes, $mepssi);
                break;

            // ===== Type "Domaines" =====
            case 'domaines':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'domaineId' => $entity->getId() ]);
                $demandes = $em->getRepository(DemandeIntervention::class)->restitutionDemandes($composants);
                $mepssi = $em->getRepository(MepSsi::class)->restitutionMepSsi($composants);
                $demandes = self::fusionDemandesMep($demandes, $mepssi);
                break;

            // ===== Type "Pilotes" =====
            case 'pilotes':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'piloteId' => $entity->getId() ]);
                $demandes = $em->getRepository(DemandeIntervention::class)->restitutionDemandes($composants);
                $mepssi = $em->getRepository(MepSsi::class)->restitutionMepSsi($composants);
                $demandes = self::fusionDemandesMep($demandes, $mepssi);
                break;

            // ===== Type "Équipes" =====
            case 'equipes':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'equipeId' => $entity->getId() ]);
                $demandes = $em->getRepository(DemandeIntervention::class)->restitutionDemandes($composants);
                $mepssi = $em->getRepository(MepSsi::class)->restitutionMepSsi($composants);
                $demandes = self::fusionDemandesMep($demandes, $mepssi);
                break;

            // ===== Type "Bureaux de rattachement" =====
            case 'bureaux-rattachement':
                $composants = $em->getRepository(Composant::class)->listeComposants([ 'bureauRattachementId' => $entity->getId() ]);
                $demandes = $em->getRepository(DemandeIntervention::class)->restitutionDemandes($composants);
                $mepssi = $em->getRepository(MepSsi::class)->restitutionMepSsi($composants);
                $demandes = self::fusionDemandesMep($demandes, $mepssi);
                break;
        }

        // On rend la vue
        return $this->render('restitution/fiche/includes/_demandes.html.twig', [ 'demandes' => $demandes ]);
    }

    /**
     * Liste des Services identifiés dans GESIP comme étant des services pilotes et associés aux composants.
     * @param array $composants
     * @return array
     */
    private function liensEquipes(array $composants) : array
    {
        return [
            'type' => [
                'titre' => 'Liens avec Equipe CS',
                'type' => 'equipes'
            ],
            'donnees' => $this->getDoctrine()->getManager()->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->innerJoin(Composant::class, 'c', Join::WITH, 'c.equipe = s.id')
                ->orderBy('s.label', 'ASC')
                ->andWhere('c IN (:composants)')->setParameter('composants', $composants)
                ->getQuery()->getResult()
        ];
    }

    /**
     * Liste des Pilotes titulaires et suppléants identifiés dans GESIP comme étant des pilotes et associés aux
     * composants
     * @param array $composants
     * @return array
     */
    private function liensPilotes(array $composants) : array
    {
        return [
            'type' => [
                'titre' => 'Liens avec Pilote',
                'type' => 'pilotes'
            ],
            'donnees' => $this->getDoctrine()->getManager()->createQueryBuilder()
                ->select('p')
                ->from(Pilote::class, 'p')
                ->innerJoin(Composant::class, 'c', Join::WITH, 'c.pilote = p.id OR c.piloteSuppleant = p.id')
                ->orderBy('p.nom', 'ASC')
                ->andWhere('c IN (:composants)')->setParameter('composants', $composants)
                ->getQuery()->getResult()
        ];
    }

    /**
     * Liste des Services identifiés dans GESIP comme étant des services ‘Bureau de rattachement’ et associés aux
     * composants
     * @param array $composants
     * @return array
     */
    private function liensBureauxRattachement(array $composants) : array
    {
        return [
            'type' => [
                'titre' => 'Liens avec Bureau de rattachement',
                'type' => 'bureaux-rattachement'
            ],
            'donnees' => $this->getDoctrine()->getManager()->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->innerJoin(Annuaire::class, 'a', Join::WITH, 'a.service = s.id')
                ->orderBy('s.label', 'ASC')
                ->andWhere('s.estBureauRattachement = :bureauRattachement')->setParameter('bureauRattachement', 1)
                ->andWhere('a.composant IN (:composants)')->setParameter('composants', $composants)
                ->getQuery()->getResult()
        ];
    }

    /**
     * Liste des Services identifiés dans GESIP comme étant des services ‘Bureau de rattachement’ et associés aux
     * composants
     * @param array $composants
     * @return array
     */
    private function liensDomaines(array $composants) : array
    {
        return [
            'type' => [
                'titre' => 'Liens avec Domaine',
                'type' => 'domaines'
            ],
            'donnees' => $this->getDoctrine()->getManager()->createQueryBuilder()
                ->select('d')
                ->from(Domaine::class, 'd')
                ->innerJoin(Composant::class, 'c', Join::WITH, 'c.domaine = d.id')
                ->orderBy('d.label', 'ASC')
                ->andWhere('c IN (:composants)')->setParameter('composants', $composants)
                ->getQuery()->getResult()
        ];
    }

    /**
     * Liste des services identifiés dans GESIP comme étant des services exploitants et associés aux composants
     * @param array $composants
     * @return array
     */
    private function liensESI(array $composants) : array
    {
        return [
            'type' => [
                'titre' => 'Liens avec ESI',
                'type' => 'esi'
            ],
            'donnees' => $this->getDoctrine()->getManager()->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->innerJoin(Composant::class, 'c', Join::WITH, 'c.exploitant = s.id')
                ->orderBy('s.label', 'ASC')
                ->andWhere('c IN (:composants)')->setParameter('composants', $composants)
                ->getQuery()->getResult()
        ];
    }


    /**
     * @Route("/gestion/restitution/{type}/{export?}", name="gestion-restitutions-listing", requirements={
     *     "type"="derniers-gesip|composants-sans-equipe-ou-pilote|composants-sans-esi|composants-sans-moe-ou-moa",
     *     "export"="|xlsx|pdf"
     * })
     *
     * @param Request     $request
     * @param string      $type
     * @param string|null $export
     * @param Pdf         $pdf
     *
     * @return Response
     */
    public function listingRestitutionGestion(Request $request, string $type, ?string $export, Pdf $pdf) : Response
    {
        // On déclare quelques variables
        $em = $this->getDoctrine()->getManager();
        $donnees = [];

        // On va chercher les informations en fonction du type demandé
        switch ($type) {
            // ===== Type "Derniers gesip" ====
            case 'derniers-gesip':
                // On initialise quelques variables
                $composantsRepository = $em->getRepository(Composant::class);
                $dateLimite = (new \DateTime())->sub(new \DateInterval('P600D')); // maintenant - 600 jours

                // On récupère les composants ayant eu une intervention entre aujourd'hui et 600 jours
                $composantsIdsAvecInterventionRecente = $composantsRepository->createQueryBuilder('c')
                    ->leftJoin(DemandeIntervention::class, 'di', Join::WITH, 'di.composant = c.id')
                    ->where('di.dateFinMax > :limite')->setParameter('limite', $dateLimite)
                    ->getQuery()->getArrayResult();
                $composantsIdsAvecInterventionRecente = array_column($composantsIdsAvecInterventionRecente, 'id');

                // On récupère les composants n'ayant pas eu une intervention depuis 600 jours
                $requete = $em->getRepository(Composant::class)
                    ->createQueryBuilder('c')
                    ->select(['c', 'exploitant', 'equipe', 'pilote', 'bureau', 'di.dateFinMax as derniereDi'])
                    ->leftJoin('c.exploitant', 'exploitant')
                    ->leftJoin('c.equipe', 'equipe')
                    ->leftJoin('c.pilote', 'pilote')
                    ->leftJoin('c.bureauRattachement', 'bureau')
                    ->leftJoin(DemandeIntervention::class, 'di', Join::WITH, 'di.composant = c.id')
                    ->andWhere('di.dateFinMax IS NULL OR di.dateFinMax < :limite')->setParameter('limite', $dateLimite)
                    ->andWhere('c.archiveLe IS NULL')
                    ->addOrderBy('c.label', 'ASC')
                    ->addOrderBy('di.dateFinMax', 'ASC');
                if (count($composantsIdsAvecInterventionRecente) != 0) {
                    $requete->andWhere('c.id NOT IN (:composantsIds)')->setParameter('composantsIds', $composantsIdsAvecInterventionRecente);
                }
                $donnees = $requete->getQuery()->getArrayResult();
                break;

            // ===== Type "Composants sans équipe et/ou pilote" ====
            case 'composants-sans-equipe-ou-pilote':
                // On récupère les composants
                $donnees = $em->getRepository(Composant::class)
                    ->createQueryBuilder('c')
                    ->select(['c', 'exploitant', 'domaine', 'bureau'])
                    ->leftJoin('c.exploitant', 'exploitant')
                    ->leftJoin('c.domaine', 'domaine')
                    ->leftJoin('c.bureauRattachement', 'bureau')
                    ->where('c.equipe IS NULL OR c.pilote IS NULL')
                    ->andWhere('c.archiveLe IS NULL')
                    ->orderBy('c.label', 'ASC')
                    ->getQuery()->getResult();
                break;

            // ===== Type "Composants sans ESI" ====
            case 'composants-sans-esi':
                // On récupère les composants
                $donnees = $em->getRepository(Composant::class)
                    ->createQueryBuilder('c')
                    ->select(['c', 'domaine', 'bureau', 'equipe', 'pilote'])
                    ->leftJoin('c.domaine', 'domaine')
                    ->leftJoin('c.bureauRattachement', 'bureau')
                    ->leftJoin('c.equipe', 'equipe')
                    ->leftJoin('c.pilote', 'pilote')
                    ->where('c.exploitant IS NULL')
                    ->andWhere('c.archiveLe IS NULL')
                    ->orderBy('c.label', 'ASC')
                    ->getQuery()->getResult();
                break;

            // ===== Type "Composants sans MOE et/ou MOA" ====
            case 'composants-sans-moe-ou-moa':
                // On récupère les composants ayant une MOE
                $composantsIdsMOE = $em->createQueryBuilder()
                    ->select('c.id')
                    ->from(Annuaire::class, 'a')
                    ->leftJoin('a.composant', 'c')
                    ->leftJoin('a.mission', 'mission')
                    ->where('mission.label LIKE :moe')->setParameter('moe', '%MOE%')
                    ->getQuery()->getArrayResult();
                $composantsIdsMOE = array_column($composantsIdsMOE, 'id');

                // On récupère les composants ayant une MOA
                $composantsIdsMOA = $em->createQueryBuilder()
                    ->select('c.id')
                    ->from(Annuaire::class, 'a')
                    ->leftJoin('a.composant', 'c')
                    ->leftJoin('a.mission', 'mission')
                    ->where('mission.label LIKE :moa')->setParameter('moa', '%MOA%')
                    ->getQuery()->getArrayResult();
                $composantsIdsMOA = array_column($composantsIdsMOA, 'id');

                // Ids des composants ayant MOA et MOE de renseignée
                $composantsIdsAvecMOEetMOA = array_intersect($composantsIdsMOE, $composantsIdsMOA);

                // On récupère les composants
                $donnees = $em->getRepository(Composant::class)
                    ->createQueryBuilder('c')
                    ->select(['c', 'esi', 'domaine', 'bureau', 'equipe', 'pilote', 'annuaire', 'annuaire_mission'])
                    ->leftJoin('c.exploitant', 'esi')
                    ->leftJoin('c.domaine', 'domaine')
                    ->leftJoin('c.bureauRattachement', 'bureau')
                    ->leftJoin('c.equipe', 'equipe')
                    ->leftJoin('c.pilote', 'pilote')
                    ->leftJoin('c.annuaire', 'annuaire')
                    ->leftJoin('annuaire.mission', 'annuaire_mission')
                    ->where('c.id NOT IN (:composantsIds)')->setParameter('composantsIds', $composantsIdsAvecMOEetMOA)
                    ->andWhere('c.archiveLe IS NULL')
                    ->orderBy('c.label', 'ASC')
                    ->getQuery()->getResult();
                break;
        }

        // Si nous avons demandé un export en XLSX
        if ($export === self::EXPORT_XLSX) {
            // On défini notre variable stockants le formattage pour le fichier XLSX
            $xlsxDonnees = [];

            // On défini notre fichier en fonction du type
            switch ($type) {
                // Si le type est bizarre, on provoque une erreur.
                default:
                    throw new NotFoundHttpException();

                // ===== Type "Derniers gesip" ====
                case 'derniers-gesip':
                    $titre = '%s composant(s) dont le dernier GESIP remonte à 600 jours';
                    $colonnes = [
                        'Composant',
                        'Équipe CS',
                        'Pilote',
                        'Rattachement',
                        'Dernière intervention',
                    ];
                    $xlsxDonnees = array_map(function ($value) {
                        return [
                            $value[0]['label'],
                            $value[0]['equipe'] ? $value[0]['equipe']['label'] : null,
                            $value[0]['pilote'] ? ChaineDeCaracteres::prenomNomAbrege($value[0]['pilote']['prenom'], $value[0]['pilote']['nom']) : null,
                            $value[0]['bureauRattachement'] ? $value[0]['bureauRattachement']['label'] : null,
                            $value['derniereDi'] ? $value['derniereDi']->format('d/m/Y') : null,
                        ];
                    }, $donnees);
                    break;

                // ===== Type "Composants sans équipe et/ou pilote" ====
                case 'composants-sans-equipe-ou-pilote':
                    $titre = '%s composant(s) sans équipe ou pilote';
                    $colonnes = [
                        'Composant',
                        'ESI',
                        'Domaine',
                        'Rattachement',
                    ];
                    $xlsxDonnees = array_map(function (Composant $value) {
                        return [
                            $value->getLabel(),
                            $value->getExploitant() ? $value->getExploitant()->getLabel() : null,
                            $value->getDomaine() ? $value->getDomaine()->getLabel() : null,
                            $value->getBureauRattachement() ? $value->getBureauRattachement()->getLabel() : null,
                        ];
                    }, $donnees);
                    break;

                // ===== Type "Composants sans ESI" ====
                case 'composants-sans-esi':
                    $titre = '%s composant(s) sans ESI de rattachement';
                    $colonnes = [
                        'Composant',
                        'Domaine',
                        'Rattachement',
                        'Équipe CS',
                        'Pilote',
                    ];
                    $xlsxDonnees = array_map(function (Composant $value) {
                        return [
                            $value->getLabel(),
                            $value->getDomaine() ? $value->getDomaine()->getLabel() : null,
                            $value->getBureauRattachement() ? $value->getBureauRattachement()->getLabel() : null,
                            $value->getEquipe() ? $value->getEquipe()->getLabel() : null,
                            $value->getPilote() ? $value->getPilote()->getNomCompletCourt() : null,
                        ];
                    }, $donnees);
                    break;

                // ===== Type "Composants sans MOE et/ou MOA" ====
                case 'composants-sans-moe-ou-moa':
                    $titre = '%s composant(s) sans MOE et/ou MOA';
                    $colonnes = [
                        'Composant',
                        'ESI',
                        'Domaine',
                        'Rattachement',
                        'Équipe CS',
                        'Pilote',
                        'Mission existante',
                    ];
                    $xlsxDonnees = array_map(function (Composant $value) {

                        // On cherche la mission MOA ou MOE déjà présente dans le composant
                        $missionTrouvee = [];
                        /** @var Annuaire $annuaire */
                        foreach ($value->getAnnuaire() as $annuaire) {
                            $label = $annuaire->getMission()->getLabel();

                            if (strpos($label, 'MOA') !== false || strpos($label, 'MOE') !== false) {
                                $missionTrouvee[] = $label;
                            }
                        }
                        $missionTrouvee = array_unique($missionTrouvee);

                        // On formate notre ligne
                        return [
                            $value->getLabel(),
                            $value->getExploitant() ? $value->getExploitant()->getLabel() : null,
                            $value->getDomaine() ? $value->getDomaine()->getLabel() : null,
                            $value->getBureauRattachement() ? $value->getBureauRattachement()->getLabel() : null,
                            $value->getEquipe() ? $value->getEquipe()->getLabel() : null,
                            $value->getPilote() ? $value->getPilote()->getNomCompletCourt() : null,
                            implode(', ', $missionTrouvee)
                        ];
                    }, $donnees);
                    break;
            }

            // On exporte en Xlsx
            return $this->exportXlsx($titre, $colonnes, $xlsxDonnees);

            // Si nous avons demandé un export en PDF
        } elseif ($export === self::EXPORT_PDF) {
            // On génère la vue html
            $html = $this->renderView('restitution/listing/gestion/' . $type . '.pdf.html.twig', [
                'baseAssets' => $this->getParameter('kernel.project_dir') . '/public',
                'type' => $type,
                'donnees' => $donnees
            ]);

            // On crée notre fichier pdf associé au html généré précédemment, que l'on renvoi au navigateur
            return new PdfResponse($pdf->getOutputFromHtml($html), 'export.pdf');
        }

        // Sinon, on rend la vue normalement
        return $this->render('restitution/listing/gestion/' . $type . '.html.twig', [
            'type' => $type,
            'donnees' => $donnees
        ]);
    }

    /**
     * Fonction permettant d'exporter des données au format XSLX en passant le titre, le nom des colonnes et les
     * données.
     *
     * @param string $titre
     * @param array  $colonnes
     * @param array  $donnees
     *
     * @return Response
     */
    private function exportXlsx(string $titre, array $colonnes, array $donnees) : Response
    {
        // On reformatte le titre
        $titre = sprintf($titre, count($donnees));

        // Construit le fichier xlsx
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Gesip')
            ->setTitle($titre);

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
        $activeWorksheet = $spreadsheet->getActiveSheet()->setTitle((new \DateTime())->format('Y-m-d'));

        // Ajoute le titre du composant représentant le label du composant
        $activeWorksheet->getCell('A1')
            ->setValue($titre)
            ->getStyle()->applyFromArray($header1);
        $activeWorksheet->getRowDimension(1)->setRowHeight(35);
        $activeWorksheet->mergeCellsByColumnAndRow(1, 1, count($colonnes), 1);

        // On ajoute les colonnes
        $derniereColonne = chr(ord('A') + count($colonnes) - 1);
        $derniereLigne = count($donnees) + 2;
        $activeWorksheet->fromArray($colonnes, null, 'A2')
            ->getStyle("A2:{$derniereColonne}2")
            ->applyFromArray($header2);
        $activeWorksheet->getRowDimension(2)->setRowHeight(25);

        // On ajoute les données
        $activeWorksheet->fromArray($donnees, null, 'A3')
            ->getStyle("A3:{$derniereColonne}{$derniereLigne}")
            ->applyFromArray($header3);

        // On redimensionne toutes les cellules automatiquement
        for ($ascii = ord('A'); $ascii <= ord($derniereColonne); $ascii++) {
            $activeWorksheet->getColumnDimension(chr($ascii))->setAutoSize(true);
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
     * Fonction permettant de fusionner les tableaux de demandes et mepssi
     *
     * @param array  $demandes
     * @param array  $mepssi
     *
     * @return array
     */
    private function fusionDemandesMep(array $demandes, array $mepssi) : array
    {
        $fusion = [];
        $iDemande = 0;
        $iMep = 0;
        while (($iDemande < count($demandes)) || ($iMep < count($mepssi))) {
            if ($iDemande == count($demandes)) {
                $classe = 'mepssi';
            } elseif ($iMep == count($mepssi)) {
                $classe = 'demande';
            } elseif ($demandes[$iDemande]->getDateDebut() > ($mepssi[$iMep][0]->getMepDebut() ?? $mepssi[$iMep][0]->getMes())) {
                $classe = 'demande';
            } else {
                $classe = 'mepssi';
            }
            if ($classe == 'demande') {
                $fusion[] = [
                    'classe'       => 'demande',
                    'dateDebut'    => $demandes[$iDemande]->getDateDebut(),
                    'data'         => $demandes[$iDemande]
                ];
                $iDemande++;
            } else {
                $fusion[] = [
                    'classe'       => 'mepssi',
                    'dateDebut'    => ($mepssi[$iMep][0]->getMepDebut() ?? $mepssi[$iMep][0]->getMes()),
                    'data'         => $mepssi[$iMep][0]
                ];
                $iMep++;
            }
        }
        return $fusion;
    }

    /**
     * @Route("/gestion/restitution/services-composants-balfs", name="gestion-restitutions-services-composants-balfs")
     */
    public function listingBalfComposants(Request $request): Response
    {
        // On initialise nos variables
        $services = [];
        $annuaires = [];

        // On récupère l'Entity Manager ainsi que le formulaire que l'on traite
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(RechercheBalfServiceType::class);

        $form->handleRequest($request);

        // Si celui-ci a été soumis, alors on va chercher les données en base
        if ($form->isSubmitted() && $form->isValid()) {
            $services = $em->getRepository(Service::class)
                ->rechercheParBalf($form->get('balf')->getData());
            $idsServices = array_column($services, 'id');

            $annuaires = $em->getRepository(Annuaire::class)
                ->rechercheParBalf($form->get('balf')->getData(), $idsServices);
        }

        // On renvoi les informations
        return $this->render('restitution/listing/composantsBalf.html.twig', [
            'form' => $form->createView(),
            'services' => $services,
            'annuaires' => $annuaires
        ]);
    }
}
