<?php

namespace App\Controller\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\Service;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ReferentielFluxController extends AbstractController
{

    /** @var Service */
    private $serviceCourant;

    /**
     * Constructeur de ReferentielFluxController
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->serviceCourant = $security->getUser();
    }

    /**
     * @Route("/fiabilisation/flux", name="fiabilisation-flux-index")
     */
    public function index(): Response
    {
        // On récupère les informations dont nous avons besoin pour la suite
        $composantRepository = $this->getDoctrine()->getRepository(Composant::class);

        /** @var Composant[] $composants */
        if ($this->serviceCourant->getEstPilotageDme() || in_array(Service::ROLE_ADMIN, $this->serviceCourant->getRoles())) {
            $composants = $composantRepository->findBy(['archiveLe' => null], [ 'label' => 'asc' ]);
        } else {
            $composants = $composantRepository->composantsMoeService($this->serviceCourant);
        }

        // On génère la vue
        return $this->render('fiabilisation/flux/index.html.twig', [
            'composants' => $composants
        ]);
    }

    /**
     * @Route("/fiabilisation/flux/demandes", name="fiabilisation-flux-index-demandes")
     */
    public function listeDemandes(): Response
    {
        // On récupère l'EntityManager
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        // On récupère le service courant ainsi
        /** @var Service $serviceCourant */
        $serviceCourant = $this->getUser();

        // On récupère les demandes qu'il a réalisés
        $demandes = $em->getRepository(DemandeReferentielFlux::class)->findAllDemandes($serviceCourant);

        // Si le service n'a pas de demande, on redirige l'utilisateur vers la page d'index de la fiabilisation du référentiel des flux
        if (count($demandes) === 0) {
            return $this->redirectToRoute('fiabilisation-flux-index');
        }

        // On génère la vue
        return $this->render('fiabilisation/flux/listeDemandes.html.twig', [
            'demandes' => $demandes
        ]);
    }

    /**
     * Vérification si le service est bien présent en tant que MOE du composant passé en paramètre
     * Si le service n'a pas le droit, on envoie une exception pour indiquer que l'accès est interdit.
     * @param Composant $composant
     * @return bool
     */
    private function verificationAccessComposant(Composant $composant): void
    {
        // Si le service n'est pas un pilote et un admin, on contrôle les accès au composant
        if (!$this->serviceCourant->getEstPilotageDme() && !in_array(Service::ROLE_ADMIN, $this->serviceCourant->getRoles())) {
            /** @var Composant[] $composants */
            $composants = $this->getDoctrine()->getRepository(Composant::class)
                ->composantsMoeService($this->serviceCourant);

            if (!in_array($composant, $composants)) {
                throw new AccessDeniedHttpException();
            }
        }
    }

    /**
     * @Route("/fiabilisation/flux/entrants/{id}", name="fiabilisation-flux-entrants", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function showFluxEntrants(int $id): Response
    {
        // On récupère le composant avec ses flux entrants
        /** @var Composant $composant */
        $composant = $this->getDoctrine()->getRepository(Composant::class)
            ->findAvecFluxEntrants($id);

        // On vérifie que le service courant à la possibilité d'ajouter une demande pour ce composant
        $this->verificationAccessComposant($composant);

        // On récupère les demandes en cours
        $demandesEnAttente = $this->getDoctrine()->getRepository(DemandeReferentielFlux::class)
            ->findAllDemandes($this->serviceCourant, $composant);

        // On génère un tableau bien formée pour combiner à la fois la liste des composants du flux ainsi que les demandes
        $listeComposants = [];
        /** @var Composant $c */
        foreach ($composant->getFluxEntrants() as $c) {
            $listeComposants[$c->getId()] = [
                'type' => null,
                'composantId' => $c->getId(),
                'composantLabel' => $c->getLabel()
            ];
        }
        /** @var DemandeReferentielFlux $d */
        foreach ($demandesEnAttente as $d) {
            $listeComposants[$d->getComposantTarget()->getId()] = [
                'type' => $d->getType(),
                'composantId' => $d->getComposantTarget()->getId(),
                'composantLabel' => $d->getComposantTarget()->getLabel()
            ];
        }

        // On trie le tableau par rapport au label
        uasort($listeComposants, function ($a, $b) {
            return strnatcmp($a['composantLabel'], $b['composantLabel']);
        });

        // On génère la vue
        return $this->render('fiabilisation/flux/demandeService.html.twig', [
            'type' => 'entrants',
            'composant' => $composant,
            'flux' => $listeComposants,
        ]);
    }

    /**
     * @Route("/fiabilisation/flux/sortants/{id}", name="fiabilisation-flux-sortants", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function showFluxSortants(int $id): Response
    {
        // On récupère le composant avec ses flux sortants
        /** @var Composant $composant */
        $composant = $this->getDoctrine()->getRepository(Composant::class)
            ->findAvecFluxSortants($id);

        // On vérifie que le service courant à la possibilité d'ajouter une demande pour ce composant
        $this->verificationAccessComposant($composant);

        // On récupère les demandes en cours
        $demandesEnAttente = $this->getDoctrine()->getRepository(DemandeReferentielFlux::class)
            ->findAllDemandes($this->serviceCourant, null, $composant);

        // On génère un tableau bien formée pour combiner à la fois la liste des composants du flux ainsi que les demandes
        $listeComposants = [];
        /** @var Composant $c */
        foreach ($composant->getFluxSortants() as $c) {
            $listeComposants[$c->getId()] = [
                'type' => null,
                'composantId' => $c->getId(),
                'composantLabel' => $c->getLabel()
            ];
        }
        /** @var DemandeReferentielFlux $d */
        foreach ($demandesEnAttente as $d) {
            $listeComposants[$d->getComposantSource()->getId()] = [
                'type' => $d->getType(),
                'composantId' => $d->getComposantSource()->getId(),
                'composantLabel' => $d->getComposantSource()->getLabel()
            ];
        }

        // On trie le tableau par rapport au label
        uasort($listeComposants, function ($a, $b) {
            return strnatcmp($a['composantLabel'], $b['composantLabel']);
        });

        // On génère la vue
        return $this->render('fiabilisation/flux/demandeService.html.twig', [
            'type' => 'sortants',
            'composant' => $composant,
            'flux' => $listeComposants,
        ]);
    }
}
