<?php

namespace App\Controller;

use App\Entity\Composant;
use App\Entity\References\NatureImpact;
use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Form\Demande\InterventionType;
use App\Form\Demande\RechercheDemandeInterventionType;
use App\Form\RechercheInterventionCopierType;
use App\Form\RechercheInterventionType;
use App\Repository\DemandeInterventionRepository;
use App\Workflow\Actions\ActionEnregistrer;
use App\Workflow\Actions\ActionEnvoyer;
use App\Workflow\Actions\ActionEnvoyerRenvoie;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatBrouillon;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use App\Workflow\Etats\EtatDebut;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatTerminee;
use App\Workflow\MachineEtat;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class DemandeInterventionController extends AbstractController
{

    /** @var MailerInterface */
    private $mailer;

    /** @var Security $security */
    private $security;

    /**
     * Constructeur
     * @param MailerInterface $mailer
     * @param Security $security
     */
    public function __construct(MailerInterface $mailer, Security $security)
    {
        $this->mailer = $mailer;
        $this->security = $security;
    }

    /**
     * @Route("/", name="accueil")
     */
    public function showTableauBordDemandes(Request $request): Response
    {

        // r??cup??re le formulaire et les filtres qu'il contient
        $filters = [];
        $form = $this->createForm(RechercheDemandeInterventionType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // filtrage par status
            $filters['status'] = [];
            $filters['retard'] = [];
            $data = $form->getData();
            if (!empty($data['status'])) {
                foreach (explode(',', $data['status']) as $statusName) {
                    $filters['status'][] = 'App\\Workflow\\Etats\\' . $statusName;
                }
            }
            // Ajoute le filtrage 'urgent'
            if (!empty($data['demandeUrgente'])) {
                $filters['natureIntervention'] = DemandeIntervention::NATURE_URGENT;
            }
            // Ajoute le filtrage consultation n??gative
            if (!empty($data['retourConsultationNegatif'])) {
                $filters['status'] = array_intersect($filters['status'], [
                    EtatConsultationEnCours::class,
                    EtatConsultationEnCoursCdb::class,
                    EtatInstruite::class,
                ]);
                $filters['retourConsultationNegatif'] = true;
            }
            // Ajoute le filtrage en retard
            if (!empty($data['reponseEnRetard'])) {
                $filters['retard'] = true;
            }
            // On ajoute le filtre ??quipe
            if ($this->isGranted(Service::ROLE_GESTION)) {
                $filters['equipe'] = $data['equipe'];
            }
        }

        // Ajout du filtrage en fonction du r??le de l'utilisateur connect?? (demandePar, Pilote, exploitants, ...)
        if (!$this->isGranted(Service::ROLE_ADMIN) && !$this->isGranted(Service::ROLE_DME)) {
            $serviceConnecte = $this->security->getUser();
            if ($this->isGranted(Service::ROLE_INTERVENANT)) {
                // Si le service connect?? est intervenant
                if (empty($data['status']) || in_array($data['status'], ['EtatAccordee', 'EtatInterventionEnCours','EtatConsultationEnCours,EtatConsultationEnCoursCdb,EtatInstruite'])) {
                    // Pour les filtres Tous, Accordee, Encours, OU En cours de consultation on accepte ?? la fois pas demandePar et par exploitant
                    $filters['demandeParOrexploitant'] = $serviceConnecte;
                } elseif (in_array($data['status'], ['EtatAnalyseEnCours', 'EtatRenvoyee'])) {
                    // Pour les filtres AnalyseEnCours et Renvoyee, on filtre uniquement avec le champ demandePar
                    $filters['demandeParOrexploitant'] = $serviceConnecte;
                } else {
                    // Sinon on filtre par service exploitant
                    $filters['exploitant'] = $serviceConnecte;
                }
            } else {
                // Pour les invit??s, rien d'autres que les interventions en cours
                $filters['status'] = [EtatInterventionEnCours::class];
            }
        }

        // R??cup??re la liste des demandes ?? afficher
        $demandeInterventions = $this->getDoctrine()->getManager()
            ->getRepository(DemandeIntervention::class)
            ->rechercheDemandeInterventionTableauBord($filters);

        // Construction de la r??ponse
        return $this->render('tableau-bord-demandes.html.twig', [
            'demandeInterventions'  => $demandeInterventions,
            'form'                  => $form->createView()
        ]);
    }

    /**
     * @Route("/demandes/{id}", name="demandes-visualisation", requirements={"id"="\d+"})
     */
    public function visualiserDemande(DemandeIntervention $demandeIntervention): Response
    {
        // Si la demande est en "Brouillon"
        if ($demandeIntervention->getStatus() == EtatBrouillon::class) {
            // Et si l'utilisateur est celui qui a fait la demande, alors on le redirige vers la demande
            if ($demandeIntervention->getDemandePar() == $this->getUser()) {
                return $this->redirectToRoute('demandes-modification', ['id' => $demandeIntervention->getId()]);
            // Et si l'utilisateur n'est pas celui qui a fait la demande, on le redirige vers l'accueil
            } else {
                $this->addFlash('danger', "Cette demande d'intervention ne peut ??tre visualis??e.");
                return $this->redirectToRoute('accueil');
            }
        }

        // Si la demande est renvoy??e et que l'utilisateur est le demandeur, alors on le redirige vers la page de modification lors d'un renvoie
        if ($demandeIntervention->getStatus() == EtatRenvoyee::class && $demandeIntervention->getDemandePar() == $this->getUser()) {
            return $this->redirectToRoute('demandes-renvoyees-modification', ['id' => $demandeIntervention->getId()]);
        }

        // Dans tous les autres cas, on charge la vue
        $demandeIntervention->getMachineEtat()->setEntityManager($this->getDoctrine()->getManager());
        return $this->render('demandes/vue.html.twig', [
            'demandeIntervention'   => $demandeIntervention,
        ]);
    }

    /**
     * @Route("/demande/{numero}", name="visualisation-demande-exterieure")
     * @param DemandeIntervention $demandeIntervention
     * @return Response
     */
    public function visualiserDemandeExterieure(DemandeIntervention $demandeIntervention): Response
    {
        // Si la demande est en "Brouillon"
        if ($demandeIntervention->getStatus() == EtatBrouillon::class) {
            throw new NotFoundHttpException();
        }

        // Dans tous les autres cas, on charge la vue
        $demandeIntervention->getMachineEtat()->setEntityManager($this->getDoctrine()->getManager());
        return $this->render('demandes/vue.html.twig', [
            'demandeIntervention'   => $demandeIntervention,
        ]);
    }

    /**
     * @Route("/demandes/{id}/vue-action/{action}", name="demandes-visualisation-vue-action", requirements={"id"="\d+"})
     */
    public function vueActionDemande(DemandeIntervention $demandeIntervention, string $action): Response
    {
        // Si la demande est en "Brouillon"
        if ($demandeIntervention->getStatus() == EtatBrouillon::class) {
            // Et si l'utilisateur est celui qui a fait la demande, alors on le redirige vers la demande
            if ($demandeIntervention->getDemandePar() == $this->getUser()) {
                return $this->redirectToRoute('demandes-modification', ['id' => $demandeIntervention->getId()]);
                // Et si l'utilisateur n'est pas celui qui a fait la demande, on le redirige vers l'accueil
            } else {
                $this->addFlash('danger', "Cette demande d'intervention ne peut ??tre visualis??e.");
                return $this->redirectToRoute('accueil');
            }
        }

        // Si la demande est renvoy??e et que l'utilisateur est le demandeur, alors on le redirige vers la page de modification lors d'un renvoie
        if ($demandeIntervention->getStatus() == EtatRenvoyee::class && $demandeIntervention->getDemandePar() == $this->getUser()) {
            return $this->redirectToRoute('demandes-renvoyees-modification', ['id' => $demandeIntervention ->getId()]);
        }

        // On r??cup??re la machine ?? ??tat
        $mae = $demandeIntervention->getMachineEtat($this->getUser());
        $mae->setEntityManager($this->getDoctrine()->getManager());

        // On cherche la bonne action
        foreach ($mae->getEtatActuel()->getActionsInstances() as $actionInstance) {
            $classAction = str_replace('App\Workflow\Actions\\', '', get_class($actionInstance));
            if ($classAction === $action && $actionInstance->getBoutonLibelle() !== '' && $actionInstance->peutEtreExecutee()) {
                // On g??n??re la vue par rapport ?? l'action trouv??e
                return Response::create($actionInstance->vue());
            }
        }
        // Si aucune action trouv??e, on balance une vue vide
        return Response::create();
    }

    /**
     * @Route("/demandes/creation/{id?0}", name="demandes-creation", requirements={"id"="\d+"})
     */
    public function creation(Request $request, MailerInterface $mailer, ?DemandeIntervention $demandeACopier = null): Response
    {
        // Si nous avons une copie ?? faire ?? partir de la demande pass??e en param??tre
        if ($demandeACopier !== null) {
            // On clone la demande, on vide l'historique, ainsi que les dates d'horodatage
            $copie = true;
            $demandeIntervention = clone($demandeACopier);
            $demandeIntervention->getHistoriqueStatus()->clear();
            $demandeIntervention->setAjouteLe(new \DateTime());
            $demandeIntervention->setMajLe(new \DateTime());
            $demandeIntervention->setSupprimeLe(null);
            $demandeIntervention->setStatus(EtatBrouillon::class);
            $demandeIntervention->setStatusDonnees([]);
            // Copie des impacts d??j?? pr??sents dans la demande d'intervention
            $impacts = $demandeIntervention->getImpacts()->getValues();
            $demandeIntervention->getImpacts()->clear();
            foreach ($impacts as $impact) {
                $impact = clone($impact);
                $impact->setAjouteLe(new \DateTime());
                $impact->setMajLe(new \DateTime());
                $demandeIntervention->addImpact($impact);
            }
        } else {
            // Sinon on cr??e une nouvelle demande
            $copie = false;
            $demandeIntervention = new DemandeIntervention();
        }

        // G??n??re une nouvelle demande d'intervention
        $em = $this->getDoctrine()->getManager();
        $demandeIntervention->genererNumero();
        $demandeIntervention->setDemandePar($this->getUser());
        $form = $this->createForm(InterventionType::class, $demandeIntervention);
        $form->handleRequest($request);

        // Si l'utilisateur courant n'a pas de composants de charg?? ?? partir du formulaire, alors il n'est pas autoris?? ?? poursuivre la cr??ation
        $formRendered = $form->createView();
        if (count($formRendered->offsetGet('composant')->vars['choices']) === 0) {
            $this->addFlash('danger', "Vos droits actuels dans GESIP ne vous permettent pas de cr??er une demande d'intervention.");
            return $this->redirectToRoute('accueil');
        }

        $isSubmitted = $form->isSubmitted();
        if ($isSubmitted && $form->isValid()) {
            // D??marre une transaction
            try {
                // Persistance de l'intervention
                $demandeIntervention = $form->getData();
                $em->persist($demandeIntervention);
                // Ajoute les impacts associ??s
                $impactNumber = 0;
                foreach ($demandeIntervention->getImpacts() as $impact) {
                    $impactNumber++;
                    $impact->setNumeroOrdre($impactNumber);
                    $demandeIntervention->addImpact($impact);
                    $impact->setDemande($demandeIntervention);
                    $em->persist($impact);
                }
                // On met en place le n??cessaire au niveau de la machine ?? ??tat pour ex??cuter l'action demand??e
                $modeEnregistrement = $demandeIntervention->getStatus();
                $demandeIntervention->setStatus(EtatDebut::class);
                /** @var MachineEtat $mae */
                $mae = $demandeIntervention->getMachineEtat();
                $mae->setEntityManager($this->getDoctrine()->getManager());
                $mae->setMailer($mailer);

                // Ajoute le message flash et redirige
                if ($modeEnregistrement == 'analyse-en-cours') {
                    $mae->executerAction(ActionEnvoyer::class, $request);
                    $em->flush();
                    return $this->redirectToRoute('demandes-encours-lister');
                } else {
                    $mae->executerAction(ActionEnregistrer::class, $request);
                    $em->flush();
                    return $this->redirectToRoute('demandes-brouillon-lister');
                }
            } catch (\Throwable $ex) {
                // Annulation de la transaction
                $this->addFlash(
                    'error',
                    "Une erreur est survenue lors de l'enregistrement de la demande." . $ex->getMessage()
                );
            }
        }
        // On r??cup??re les r??f??rences utiles
        $refNaturesImpact = $em->getRepository(NatureImpact::class)
            ->findBy(['supprimeLe' => null], ['label' => 'asc']);
        $refComposants = $em->getRepository(Composant::class)
            ->findBy(['archiveLe' => null], ['label' => 'asc']);

        // On rend la vue
        return $this->render('demandes/_form.html.twig', [
            'refNaturesImpact'      => $refNaturesImpact,
            'refComposants'         => $refComposants,
            'demandeIntervention'   => $demandeIntervention,
            'form'                  => $formRendered,
            'isSubmitted'           => $isSubmitted,
            'copie'                 => $copie
        ]);
    }

    /**
     * @Route("/demandes/modification/{id}", name="demandes-modification")
     */
    public function modification(DemandeIntervention $demandeIntervention, Request $request, MailerInterface $mailer): Response
    {
        // Si la demande n'est pas en ??tat brouillon.
        if ($demandeIntervention->getStatus() !== EtatBrouillon::class) {
            $this->addFlash('danger', "La demande N??{$demandeIntervention->getNumero()} ne peux pas ??tre modifi??e pour le moment.");
            return $this->redirectToRoute('demandes-visualisation', [ 'id' => $demandeIntervention->getId() ]);
        }

        // Si la demande n'a pas ??t?? cr????e par le service actuellement connect??.
        if ($demandeIntervention->getDemandePar() !== $this->getUser()) {
            $this->addFlash('danger', "Vous n'avez pas les droits pour modifier la demande N??{$demandeIntervention->getNumero()}.");
            return $this->redirectToRoute('demandes-brouillon-lister');
        }

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(InterventionType::class, $demandeIntervention);

        // R??cup??ration des collections initiales
        $initialImpacts = [];
        foreach ($demandeIntervention->getImpacts() as $impact) {
            $initialImpacts[] = $impact;
        }

        // r??cup??re les param??tres
        $form->handleRequest($request);
        $formRendered = $form->createView();

        // Si l'utilisateur courant n'a pas de composants de charg?? ?? partir du formulaire, alors il n'est pas autoris?? ?? poursuivre la cr??ation
        if (count($formRendered->offsetGet('composant')->vars['choices']) === 0) {
            $this->addFlash('danger', "Vos droits actuels dans GESIP ne vous permettent pas de modifier une demande d'intervention.");
            return $this->redirectToRoute('accueil');
        }

        $isSubmitted = $form->isSubmitted();
        if ($isSubmitted && $form->isValid()) {
            // D??marre une transaction
            try {
                // Ajoute les impacts associ??s
                $impactNumber = 0;
                foreach ($demandeIntervention->getImpacts() as $impact) {
                    $impactNumber++;
                    $impact->setNumeroOrdre($impactNumber);
                    $demandeIntervention->addImpact($impact);
                    if (empty($impact->getId())) {
                        $impact->setDemande($demandeIntervention);
                        $em->persist($impact);
                    }
                }
                // Suppression des impacts supprim??s
                foreach ($initialImpacts as $impact) {
                    if (false === $demandeIntervention->getImpacts()->contains($impact)) {
                        $em->remove($impact);
                    }
                }
                // On met en place le n??cessaire au niveau de la machine ?? ??tat pour ex??cuter l'action demand??e
                $modeEnregistrement = $demandeIntervention->getStatus();
                $demandeIntervention->setStatus(EtatDebut::class);
                /** @var MachineEtat $mae */
                $mae = $demandeIntervention->getMachineEtat();
                $mae->setEntityManager($this->getDoctrine()->getManager());
                $mae->setMailer($mailer);

                // Ajoute le message flash et redirige
                if ($modeEnregistrement == 'analyse-en-cours') {
                    $mae->executerAction(ActionEnvoyer::class, $request);
                    $em->flush();
                    return $this->redirectToRoute('demandes-encours-lister');
                } else {
                    $mae->executerAction(ActionEnregistrer::class, $request);
                    $em->flush();
                    return $this->redirectToRoute('demandes-brouillon-lister');
                }
            } catch (\Throwable $ex) {
                // Annulation de la transaction et renvoi d'un message d'erreur.
                $this->addFlash(
                    'error',
                    "Une erreur est survenue lors de la modification de la demande."
                );
            }
        }
        // On r??cup??re les r??f??rences utiles
        $refNaturesImpact = $em->getRepository(NatureImpact::class)
            ->findBy(['supprimeLe' => null], ['label' => 'asc']);
        $refComposants = $em->getRepository(Composant::class)
            ->findBy(['archiveLe' => null], ['label' => 'asc']);

        // G??n??ration de la page de modification d'un composant
        return $this->render('demandes/_form.html.twig', [
            'refNaturesImpact'      => $refNaturesImpact,
            'refComposants'         => $refComposants,
            'demandeIntervention'   => $demandeIntervention,
            'form'                  => $formRendered,
            'isSubmitted'           => true,
            'flagModification'      => true
        ]);
    }

    /**
     * @Route("/demandes/rechercher", name="demandes-recherche")
     */
    public function index(Request $request): Response
    {
        $form = $this->createForm(RechercheInterventionType::class);
        $form->handleRequest($request);
        $demandesIntervention = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData();
            $filters['noDraft'] = true;
            $filters['interventionsActives'] = $form->get('interventionsActives')->getData();
            $demandesIntervention = $this->getDoctrine()
                ->getRepository(DemandeIntervention::class)
                ->rechercheDemandesIntervention($filters);
        }

        return $this->render('demandes/recherche.html.twig', [
            'form'                  => $form->createView(),
            'demandesIntervention'  => $demandesIntervention
        ]);
    }

    /**
     * @Route("/demandes/copier", name="demande-copier")
     */
    public function rechercheCopier(Request $request): Response
    {
        $form = $this->createForm(RechercheInterventionCopierType::class);

        $form->handleRequest($request);
        $filters = [];
        $demandesIntervention = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData();
            $filters['noDraft'] = true;
            $demandesIntervention = $this->getDoctrine()
                ->getRepository(DemandeIntervention::class)
                ->rechercheDemandesIntervention($filters);
        }

        return $this->render('demandes/recherche-copier.html.twig', [
            'form'                  => $form->createView(),
            'demandesIntervention'  => $demandesIntervention
        ]);
    }

    /**
     * @Route("/demandes/brouillon/lister", name="demandes-brouillon-lister")
     */
    public function listerDemandesEnBrouillon(Request $request): Response
    {
        // Initialisation
        $serviceCourant = $this->getUser();

        // Requ??te donnant les brouillons du service
        $brouillons = $this->getDoctrine()
            ->getRepository(DemandeIntervention::class)
            ->listerDemandesInterventionParServiceEtats([EtatBrouillon::class], $serviceCourant);

        // On rend la vue de recherche d'une demande d'intervention en brouillon
        return $this->render('demandes/lister-brouillon.html.twig', [
            'brouillons'            => $brouillons,
            'etatLibelles'          => MachineEtat::getEtatLibelles(),
        ]);
    }

    /**
     * @Route("/demandes/en-cours/lister", name="demandes-encours-lister")
     */
    public function listerDemandesEnCours(Request $request): Response
    {
        // Initialisation
        $serviceCourant = $this->getUser();

        // Requ??te donnant les demandes en attente
        $etatLibelles = MachineEtat::getEtatLibelles();
        $enCoursFinStatus = [
            EtatAccordee::class,
            EtatInterventionEnCours::class,
            EtatTerminee::class,
        ];
        $enCoursDebutStatus = [
            EtatAnalyseEnCours::class,
            EtatRenvoyee::class,
            EtatConsultationEnCoursCdb::class,
            EtatInstruite::class,
        ];
        $enCoursStatus = array_merge($enCoursFinStatus, $enCoursDebutStatus);
        $listeDemandesEnCours = $this->getDoctrine()
            ->getRepository(DemandeIntervention::class)
            ->listerDemandesInterventionParServiceEtats($enCoursStatus, $serviceCourant);

        return $this->render('demandes/lister-encours.html.twig', [
            'encours'                       => $listeDemandesEnCours,
            'statusFiltres'                 => [
                'debut' => $enCoursDebutStatus,
                'fin'   => $enCoursFinStatus,
            ],
            'etatLibelles'                    => $etatLibelles,
        ]);
    }

    /**
     * @Route("/demandes/acceptees/lister", name="demandes-acceptees")
     */
    public function listerDemandesAcceptees(Request $request): Response
    {
        // Si nous sommes `ROLE_GESTION` nous pouvons filtrer par equipe de composant
        $form = null;
        $equipe = null;
        if ($this->security->isGranted(Service::ROLE_GESTION)) {
            $form = $this->createFormBuilder()
                ->add('equipe', EntityType::class, [
                    'class' => Service::class,
                    'choice_label' => 'label',
                    'multiple' => false,
                    'expanded' => false,
                    'required' => false,
                    'placeholder' => 'DME',
                    'label' => '??quipe',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('s')
                            ->where('s.estPilotageDme = true')
                            ->orderBy('s.label', 'ASC');
                    },
                ])
                ->getForm();

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $equipe = $form->getData()['equipe'];
            }
        }

        // On r??cup??re les demandes dans les status : "Demande accord??e" / "Intervention en cours" / "R??alis?? ?? saisir" / "Termin??e"
        $serviceConnecte = $this->getUser();
        $demandes = $this->getDoctrine()
            ->getRepository(DemandeIntervention::class)
            ->listerDemandesInterventionParServiceEtats(
                [
                    EtatAccordee::class,
                    EtatInterventionEnCours::class,
                    EtatSaisirRealise::class,
                    EtatTerminee::class,
                ],
                $this->security->isGranted(Service::ROLE_GESTION) ? null : $serviceConnecte,
                $equipe,
                $serviceConnecte
            );

        return $this->render('demandes/lister-acceptees.html.twig', [
            'demandes' => $demandes,
            'formFiltres' => ($form !== null) ? $form->createView() : null
        ]);
    }

    /**
     * @Route("/gestion/demandes/{status}", name="gestion-demandes-etat-listing", requirements={
     *     "status"="analyse-en-cours|attente-reponse|attente-reponse-cdb|attente-consultation-cdb"}
     * )
     */
    public function listerGestionDemandes(string $status): Response
    {
        // R??cup??ration des ??quipes dites "Pilotes" (Services avec estPiloteDME actif)
        $equipes = $this->getDoctrine()->getManager()
            ->getRepository(Service::class)
            ->getPilotageEquipes();

        // On r??cup??re le repository des DemandeIntervention
        /** @var DemandeInterventionRepository $diRepository */
        $diRepository = $this->getDoctrine()->getManager()->getRepository(DemandeIntervention::class);

        // R??cup??ration de l'??tat des demandes d'intervention ?? afficher
        switch ($status) {
            // Les demandes "EtatAnalyseEnCours"
            case 'analyse-en-cours':
                $dis = $diRepository->getDemandeInterventionsParEquipes(EtatAnalyseEnCours::class);
                break;
            // Les demandes "EtatConsultationEnCoursCdb"
            case 'attente-reponse-cdb':
                $dis = $diRepository->getDemandeInterventionsParEquipes(EtatConsultationEnCoursCdb::class);
                break;
            // Les demandes "EtatConsultationEnCours" (avec consultation du CDB)
            case 'attente-consultation-cdb':
                $dis = $diRepository->getDemandeInterventionsParEquipes(
                    EtatConsultationEnCours::class,
                    null,
                    function (QueryBuilder $qb) {
                        $qb->andWhere('JSON_CONTAINS(d.statusDonnees, :avecCdb) = 1');
                        $qb->setParameter('avecCdb', json_encode(['avecCdb' => true]));
                    }
                );
                break;
            // Les demandes "Demande instruite" / "Consultation en cours" / "Consultation en cours du CDB"
            case 'attente-reponse':
                $dis = $diRepository->getDemandeInterventionsParEquipes([
                   EtatInstruite::class,
                   EtatConsultationEnCours::class,
                   EtatConsultationEnCoursCdb::class
                ]);
                break;
        }
        // Dispatching des demandes par Service ??quipe de composant.
        $demandeInterventionsParEquipe = [];
        $demandeInterventionsSansEquipe = [];
        foreach ($dis as $di) {
            $equipe = $di->getComposant()->getEquipe();
            if (empty($equipe)) {
                $demandeInterventionsSansEquipe[] = $di;
            } else {
                $demandeInterventionsParEquipe[$equipe->getId()][] = $di;
            }
        }
        // Cr??ation de la r??ponse ?? partir de la vue g??n??r??e
        return $this->render('gestion/demandes/lister-encours.html.twig', [
            'equipes'                           => $equipes,
            'demandeInterventionsParEquipe'     => $demandeInterventionsParEquipe,
            'demandeInterventionsSansEquipe'    => $demandeInterventionsSansEquipe,
            'etatLibelles'                      => MachineEtat::getEtatLibelles(),
            'status'                            => $status,
        ]);
    }

    /**
     * @Route("/demandes/renvoyees/lister", name="demandes-renvoyees-lister")
     */
    public function listerDemandesRenvoyees(Request $request, ?Service $service): Response
    {
        // Si nous sommes `ROLE_GESTION` nous pouvons filtrer par equipe de composant
        $form = null;
        $equipe = null;

        if ($this->security->isGranted(Service::ROLE_GESTION)) {
            $form = $this->createFormBuilder()
                ->add('equipe', EntityType::class, [
                    'class' => Service::class,
                    'choice_label' => 'label',
                    'multiple' => false,
                    'expanded' => false,
                    'required' => false,
                    'placeholder' => 'DME',
                    'label' => '??quipe',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('s')
                            ->where('s.supprimeLe IS NULL')
                            ->andWhere('s.estPilotageDme = true')
                            ->orderBy('s.label', 'ASC');
                    },
                ])
                ->getForm();

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $equipe = $form->getData()['equipe'];
            }
        }

        // On r??cup??re les demandes
        $demandePar = $this->isGranted('ROLE_GESTION') ? null : $this->getUser();
        $demandesRenvoyees = $this->getDoctrine()->getRepository(DemandeIntervention::class)
            ->getDemandeInterventionsRenvoyeesAvecAncienStatus(EtatRenvoyee::class, $equipe, $demandePar);

        // On trie les demandes en fonction de leurs pass??s
        $demandesApresAnalyse = [];
        $demandesApresConsultation = [];
        $demandesApresAccordee = [];

        // On repartie chaque demande renvoy??e en fonction de leur historique
        foreach ($demandesRenvoyees as $demandeRenvoyee) {
            $dernierEtatTrouve = false;
            foreach ($demandeRenvoyee->getHistoriqueStatus() as $historiqueStatus) {
                if (EtatAccordee::class === $historiqueStatus->getStatus()) {
                    // Avant la demande avait ??t?? accord??e
                    $demandesApresAccordee[$demandeRenvoyee->getId()] = $demandeRenvoyee;
                    $dernierEtatTrouve = true;
                    break;
                } elseif (EtatConsultationEnCours::class === $historiqueStatus->getStatus()) {
                    // Avant la demande avait fait l'objet d'une consultation
                    $demandesApresConsultation[$demandeRenvoyee->getId()] = $demandeRenvoyee;
                    $dernierEtatTrouve = true;
                    break;
                }
            }
            if (!$dernierEtatTrouve) {
                // Si la demande n'a pas ??t?? accord??e ou fait l'objet d'une consultation avant alors l'analyse ??tait en cours
                $demandesApresAnalyse[$demandeRenvoyee->getId()] = $demandeRenvoyee;
            }
        }

        // On rend la vue de recherche d'une demande d'intervention en Renvoy??e
        return $this->render('demandes/lister-renvoye.html.twig', [
            'formFiltres' => ($form !== null) ? $form->createView() : null,
            'demandesApresAnalyse' => $demandesApresAnalyse,
            'demandesApresConsultation' => $demandesApresConsultation,
            'demandesApresAccordee' => $demandesApresAccordee,
            'etatLibelles'          => MachineEtat::getEtatLibelles(),
        ]);
    }

    /**
     * @Route("/demandes/{demande}/supprimer", name="demande-brouillon-supprimer")
     */
    public function supprimer(DemandeIntervention $demande)
    {
        $demande->setSupprimeLe(new \DateTime());
        $em = $this->getDoctrine()->getManager();
        $em->persist($demande);
        $em->flush();

        // On ajout un Message flash
        $this->addFlash(
            'success',
            "La demande a ??t?? supprim??e avec succ??s."
        );

        // On recharge la page
        return $this->redirectToRoute('demandes-brouillon-lister');
    }

    /**
     * @Route("/demandes/renvoyees/modification/{id}", name="demandes-renvoyees-modification")
     */
    public function modificationDemandeRenvoyee(DemandeIntervention $demandeIntervention, Request $request, MailerInterface $mailer): Response
    {
        // Si la demande n'est pas en ??tat brouillon.
        if ($demandeIntervention->getStatus() !== EtatRenvoyee::class) {
            $this->addFlash('danger', "La demande N??{$demandeIntervention->getNumero()} ne peux pas ??tre modifi??e pour le moment.");
            return $this->redirectToRoute('demandes-visualisation', [ 'id' => $demandeIntervention->getId() ]);
        }

        // Si nous sommes pas ROLE_GESTION ou si la demande n'a pas ??t?? cr????e par le service actuellement connect??.
        if (!$this->isGranted(Service::ROLE_GESTION) && $demandeIntervention->getDemandePar() !== $this->getUser()) {
            $this->addFlash('danger', "Vous n'avez pas les droits pour modifier la demande N??{$demandeIntervention->getNumero()}.");
            return $this->redirectToRoute('demandes-brouillon-lister');
        }

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(InterventionType::class, $demandeIntervention);

        // R??cup??ration des collections initiales
        $initialImpacts = [];
        foreach ($demandeIntervention->getImpacts() as $impact) {
            $initialImpacts[] = $impact;
        }

        // r??cup??re les param??tres
        $form->handleRequest($request);
        $formRendered = $form->createView();

        // Si l'utilisateur courant n'a pas de composants de charg?? ?? partir du formulaire, alors il n'est pas autoris?? ?? poursuivre la cr??ation
        if (count($formRendered->offsetGet('composant')->vars['choices']) === 0) {
            $this->addFlash('danger', "Vos droits actuels dans GESIP ne vous permettent pas de modifier une demande d'intervention.");
            return $this->redirectToRoute('accueil');
        }

        $isSubmitted = $form->isSubmitted();
        if ($isSubmitted && $form->isValid()) {
            // D??marre une transaction
            try {
                // Ajoute les impacts associ??s
                $impactNumber = 0;
                foreach ($demandeIntervention->getImpacts() as $impact) {
                    $impactNumber++;
                    $impact->setNumeroOrdre($impactNumber);
                    $demandeIntervention->addImpact($impact);
                    if (empty($impact->getId())) {
                        $impact->setDemande($demandeIntervention);
                        $em->persist($impact);
                    }
                }
                // Suppression des impacts supprim??s
                foreach ($initialImpacts as $impact) {
                    if (false === $demandeIntervention->getImpacts()->contains($impact)) {
                        $em->remove($impact);
                    }
                }
                $demandeIntervention->setStatus(EtatRenvoyee::class);
                /** @var MachineEtat $mae */
                $mae = $demandeIntervention->getMachineEtat();
                $mae->setEntityManager($this->getDoctrine()->getManager());
                $mae->setMailer($mailer);

                $mae->executerAction(ActionEnvoyerRenvoie::class, $request);
                $em->flush();
                return $this->redirectToRoute('demandes-encours-lister');
            } catch (\Throwable $ex) {
                // Annulation de la transaction et renvoi d'un message d'erreur.
                $this->addFlash(
                    'error',
                    "Une erreur est survenue lors de la modification de la demande."
                );
            }
        }
        // On r??cup??re les r??f??rences utiles
        $refNaturesImpact = $em->getRepository(NatureImpact::class)
            ->findBy(['supprimeLe' => null], ['label' => 'asc']);
        $refComposants = $em->getRepository(Composant::class)
            ->findBy(['archiveLe' => null], ['label' => 'asc']);

        // G??n??ration de la page de modification d'un composant
        return $this->render('demandes/_form.html.twig', [
            'refNaturesImpact'      => $refNaturesImpact,
            'refComposants'         => $refComposants,
            'demandeIntervention'   => $demandeIntervention,
            'form'                  => $formRendered,
            'isSubmitted'           => true,
            'flagModification'      => true,
            'demandeRenvoyee'       => true
        ]);
    }
}
