<?php

namespace App\Controller\Meteo;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\Meteo\Validation;
use App\Entity\Service;
use App\Entity\Meteo\Evenement;
use App\Form\Meteo\ListeEvenementsType;
use App\Form\Meteo\SaisieIndexType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class SaisieController extends AbstractController
{

    /**
     * @Route(
     *     "/meteo/saisie/{exploitant?}/{debutPeriode?}",
     *     name="meteo-saisie-index",
     *     requirements={
     *          "debutPeriode"="\d{8}",
     *     }
     * )
     */
    public function index(Request $request, ?Service $exploitant, ?string $debutPeriode): Response
    {
        // On initialise quelques variables
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(SaisieIndexType::class);
        $meteoComposants = [];
        $periodeDebut = null;
        $periodeFin = null;
        $validationDate = null;

        if (in_array(Service::ROLE_INTERVENANT, $this->getUser()->getRoles()) && !$this->getUser()->getEstServiceExploitant()) {
            throw new AccessDeniedException();
        }

        if ($exploitant && $debutPeriode) {
            $periodeDebut = \DateTime::createFromFormat('Ymd', $debutPeriode);
            $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'));

            // On récupère les composants associés au service (et sous-services si structure de rattachement)
            if ($exploitant === null) {
                $composants = $em->getRepository(Composant::class)->listeComposants();
            } else {
                $composants = $em->getRepository(Composant::class)->createQueryBuilder('c')
                    ->select('c')
                    ->join('c.exploitant', 'ex')
                    ->andWhere('c.meteoActive = true')
                    ->andWhere('c.archiveLe IS NULL')
                    ->andWhere('ex.id = :exploitant OR ex.structurePrincipale = :exploitant')
                    ->setParameter('exploitant', $exploitant)
                    ->orderBy('c.label', 'ASC')
                    ->getQuery()->getResult();
            }

            // On récupère les évènements associés aux composants
            $idsComposants = array_column($composants, 'id');
            $meteoComposants = $em->getRepository(Composant::class)->indicesMeteoComposants($idsComposants, $periodeDebut);

            // On ajoute le href pour l'affichage
            $meteoComposants = array_map(function ($meteo) use ($request, $exploitant, $debutPeriode) {
                $meteo['href'] = $this->generateUrl('meteo-composant-modifier', [
                    'composant' => $meteo['id'],
                    'dateDebut' => $debutPeriode,
                ]);

                if ($this->isGranted(Service::ROLE_GESTION)) {
                    $meteo['href'] .= '?s=' . $exploitant->getId();
                }
                return $meteo;
            }, $meteoComposants);

            // On supprime les clés (pour que cela soit considéré comme un tableau par la partie JS)
            $meteoComposants = array_values($meteoComposants);

            // On regarde si le service a validé la publication de la météo pour cette période
            $validationDate = $em->getRepository(Validation::class)->validationDateParExploitantPeriode($exploitant, $periodeDebut);
        }

        return $this->render('meteo/saisie/index.html.twig', [
            'form' => $form->createView(),
            'validationExploitant' => $validationDate,
            'exploitant' => $exploitant,
            'debutPeriode' => $periodeDebut,
            'finPeriode' => $periodeFin,
            'meteoComposants' => $meteoComposants
        ]);
    }

    /**
     * @Route("/meteo/modifier/{composant}/{dateDebut}", name="meteo-composant-modifier")
     */
    public function saisieEvenementsImpactsComposant(Composant $composant, string $dateDebut, Request $request) : Response
    {
        // Récupère le service courant, l'exploitant du composant et le service de rattachement de l'exploitant.
        $serviceConnecte = $this->getUser();
        $serviceExploitant = $composant->getExploitant();
        $serviceRattachementExploitant = $composant->getExploitant() ? $composant->getExploitant()->getStructurePrincipale() : "";

        // Interdit la saisie si l'intervenant n'est pas ROLE_GESTION et s'il n'est pas exploitant du composant et s'il n'est pas le service rattaché à l'exploitant.
        if (!$this->isGranted(Service::ROLE_GESTION) && ($serviceExploitant !== $serviceConnecte) && ($serviceRattachementExploitant !== $serviceConnecte)) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas exploitant de ce composant.');
        }

        // Interdit la saisie si un composant n'a pas de météo activée
        if (!$composant->getMeteoActive()) {
            throw new BadRequestHttpException('Le composant ne permet pas de saisir de météo (météo désactivée).');
        }

        // Récupère les dates de début et fin de période
        $dtzFr = new \DateTimeZone('Europe/Paris');
        $dtDebut = \DateTimeImmutable::createFromFormat('YmdHis', $dateDebut . '000000', $dtzFr);
        if (false === $dtDebut) {
            throw new BadRequestHttpException('La date fournie est invalide (format attendu: Ymd).');
        } elseif (4 != $dtDebut->format('w')) {
            throw new BadRequestHttpException('La date fournie devrait correspondre à un Jeudi (point de départ d\'une semaine).');
        }
        $dtFin = $dtDebut->add(new \DateInterval('P7D'))->sub(new \DateInterval('PT1S'));

        // Récupère la liste des évenements météo déjà enregistrés
        $evenements = $this->getDoctrine()
            ->getRepository(Evenement::class)
            ->listeEvenements(
                [$composant->getId()],
                $dtDebut,
                $dtFin,
                null
            );

        // Récupère le formulaire et teste si les infos recues sont valides
        $form = $this->createForm(ListeEvenementsType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Indexe les évenements existants et manageables par l'utilisateur courant
            $em = $this->getDoctrine()->getManager();
            $tmpEvenements = [];
            foreach ($evenements as $e) {
                $tmpEvenements[$e->getId()] = $e;
            }
            // Pour tous les évenements retournés par la requete
            $rawDataEvenements = ($request->request->get('liste_evenements'))['evenements'];
            $dataEvenements = ($form->getData())['evenements'];
            foreach ($dataEvenements as $index => $dataEvenement) {
                $rawDataEvenement = $rawDataEvenements[$index];
                $evenementId = !empty($rawDataEvenement['id']) ? $rawDataEvenement['id'] : null;
                $action = !empty($rawDataEvenement['action']) ? $rawDataEvenement['action'] : null;
                // verifie si l'utilisateur peut réaliser cette action sur cet evenement
                if ($action != 'creation'
                        && (empty($evenementId) || empty($tmpEvenements[$evenementId]))) {
                    throw new \Exception('Vous n\'etes peut-être pas autorisé à effectuer cette action sur cet évenement!');
                }
                // on fonction de l'action à mener sur l'évenement...
                switch ($action) {
                    case 'creation':
                        // on reprend l'evenement créé par le formulaire, on complete les champs manquants (non-gérés par le formulaire), et on persiste
                        $dataEvenement->setComposant($composant);
                        $dataEvenement->setSaisiePar($serviceConnecte);
                        $em->persist($dataEvenement);
                        // ajoute le nouvel événement à la fin
                        $evenements[] = $dataEvenement;
                        break;
                    case 'edition':
                        // on affecte l'objet evenement retourné lors de la requete précédente
                        $evenement = $tmpEvenements[$evenementId];
                        $evenement->setDebut($dataEvenement->getDebut());
                        $evenement->setFin($dataEvenement->getFin());
                        $evenement->setImpact($dataEvenement->getImpact());
                        $evenement->setTypeOperation($dataEvenement->getTypeOperation());
                        $evenement->setDescription($dataEvenement->getDescription());
                        $evenement->setCommentaire($dataEvenement->getCommentaire());
                        $em->persist($evenement);
                        break;
                    case 'suppression':
                        // on demande simplement au manager de ne plus persister cet objet
                        $em->remove($tmpEvenements[$evenementId]);
                        // Supprime l'evenements dans la collection qui permettra de retourner la liste des evenements actuels.
                        foreach ($evenements as $index => $evenement) {
                            if ($evenement->getId() == $evenementId) {
                                unset($evenements[$index]);
                            }
                        }
                        break;
                    default:
                        throw new \Exception('Cette action n\'est pas valide.');
                }
            }
            $em->flush();
            $this->addFlash(
                'success',
                "Evénements modifiés avec succès."
            );
        }

        // Recherche des demandes correspondant à cette période
        $demandesIntervention = $this->getDoctrine()
            ->getRepository(DemandeIntervention::class)
            ->listeDemandesInterventionParComposantSemaineMeteo($composant, $dtDebut, $dtFin);

        // Récupère les valeurs de la météo
        $tauxDisponibilite = null;
        $meteoIndice = null;
        $composantId = $composant->getId();
        $meteoData = $this->getDoctrine()->getRepository(Composant::class)
            ->indicesMeteoComposants([$composantId], \DateTime::createFromImmutable($dtDebut));
        if (!empty($meteoData[$composantId])) {
            $meteoIndice = $meteoData[$composantId]['indice'];
            $tauxDisponibilite = $meteoData[$composantId]['disponibilite'];
        }

        return $this->render('meteo/saisie.html.twig', [
            'composant'             => $composant,
            'debutSemaine'          => $dtDebut,
            'finSemaine'            => $dtFin,
            'evenements'            => $evenements,
            'demandesIntervention'  => $demandesIntervention,
            'form'                  => $form->createView(),
            'tauxDisponibilite'     => $tauxDisponibilite,
            'meteoIndice'           => $meteoIndice,
        ]);
    }
}
