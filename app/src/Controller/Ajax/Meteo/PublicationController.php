<?php

namespace App\Controller\Ajax\Meteo;

use App\Entity\Meteo\Validation;
use App\Entity\Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Meteo\Publication;
use App\Entity\Meteo\Evenement;
use App\Entity\Meteo\Composant;
use App\Entity\Composant as GesipComposant;

class PublicationController extends AbstractController
{
    /**
     * @Route(
     *      "/ajax/meteo/publication/{annee}",
     *      methods={"GET"},
     *      name="ajax-meteo-publication",
     * )
     */
    public function listePeriodesNonPubliees(int $annee, Request $request): JsonResponse
    {
        $reponse = [
            'annee'        => $annee,
            'total'        => 0,
            'donnees'      => []
        ];

        $listePeriodes = $this->getDoctrine()->getRepository(Publication::class)->listePeriodesNonPubliees($annee);
        foreach ($listePeriodes as $periode) {
            $reponse['donnees'][] = [
                'debut' => date_format($periode->getPeriodeDebut(), "d/m/Y"),
                'fin' => date_format($periode->getPeriodeFin(), "d/m/Y")
            ];
            $reponse['total']++;
        }

        return new JsonResponse($reponse);
    }

    /**
    * @Route("/ajax/meteo/periode/action", methods={"POST"}, name="ajax-meteo-periode-action")
    * enregistre la (dé)publication d'une météo
    */
    public function actionPeriodeMeteo(Request $request): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();

        $action = $request->request->get('action');
        $periodeDebut = \DateTime::createFromFormat("Y-m-d H:i:s", $request->request->get('debut') . " 00:00:00");
        $periodeFin = \DateTime::createFromFormat("Y-m-d H:i:s", $request->request->get('fin') . " 23:59:59");

        $dejaPublie = $em->getRepository(Publication::class)->findOneBy([ 'periodeDebut' => $periodeDebut ]);

        if ($action == 'publication') {
            // Si la semaine a publier et celle en cours, on empêche la publication
            if ((new \DateTime())->format('U') < $periodeFin->format('U')) {
                $this->addFlash('danger', 'Impossible de publier la semaine en cours.');
                return new JsonResponse([
                    'status' => 'error'
                ]);
            }

            if ($dejaPublie == null) {
                // on créé la période
                $publication = new Publication();
                $publication->setPeriodeDebut($periodeDebut);
                $publication->setPeriodeFin($periodeFin);
                $em->persist($publication);

                // On récupère les validations de publication des exploitants pour la période
                $validationPublication = [];
                $queryValidationPublication = $em->getRepository(Validation::class)->findBy([ 'periodeDebut' => $periodeDebut ]);
                foreach ($queryValidationPublication as $validation) {
                    $validationPublication[$validation->getExploitant()->getId()] = $validation->getAjouteLe();
                }

                // On récupère tous les composants
                $queryComposants = $em->getRepository(GesipComposant::class)->findBy([ 'meteoActive' => true, 'archiveLe' => null ]);
                $listeIdComposant = array_column($queryComposants, 'id');
                $composantExploitant = [];
                foreach ($queryComposants as $composant) {
                    if ($composant->getExploitant()) {
                        $composantExploitant[$composant->getId()] = $composant->getExploitant()->getId();
                    }
                }

                // on créé la météo des composants pour cette période
                $listeMeteoComposants = $em->getRepository(GesipComposant::class)->indicesMeteoComposants($listeIdComposant, $periodeDebut);
                foreach ($listeMeteoComposants as $meteoComposant) {
                    // Si il n'existe pas d'exploitant pour un composant ou si la publication a été validée par l'exploitant
                    if (!isset($composantExploitant[$meteoComposant['id']]) || isset($validationPublication[$composantExploitant[$meteoComposant['id']]])) {
                        // On ajoute la météo dans le cache.
                        $composant = new Composant();
                        $composant->setPeriodeDebut($periodeDebut);
                        $composant->setPeriodeFin($periodeFin);
                        $composant->setComposant($em->getReference(GesipComposant::class, $meteoComposant['id']));
                        $composant->setMeteo($meteoComposant['indice']);
                        $composant->setDisponibilite($meteoComposant['disponibilite']);
                        $em->persist($composant);
                    }
                }

                $em->flush();
            }
        } else {
            $derniereSemainePubliee = $em->getRepository(Publication::class)->findBy([], [ 'periodeDebut' => 'DESC' ], 1);

            if (count($derniereSemainePubliee) > 0 && $derniereSemainePubliee[0]->getPeriodeDebut()->format('d/m/Y') !== $periodeDebut->format('d/m/Y')) {
                $this->addFlash('danger', 'Il est uniquement possible de dépublier la dernière semaine publiée.');
                return new JsonResponse([
                    'status' => 'error'
                ]);
            }

            if ($dejaPublie != null) {
                // on supprime la météo des composants pour cette période
                $listeMeteoComposants = $em->getRepository(Composant::class)->listeMeteoComposantsPeriode($periodeDebut);
                foreach ($listeMeteoComposants as $meteoComposant) {
                    $em->remove($meteoComposant);
                }
                // on supprime la période
                $em->remove($dejaPublie);
                $em->flush();
            }
        }

        $this->addFlash('success', 'Demande enregistrée.');

        return new JsonResponse([
            'status' => 'success'
        ]);
    }

    /**
     * @Route(
     *     "/ajax/meteo/validations/{periodeDebut}",
     *     methods={"GET"},
     *     name="ajax-meteo-validations",
     *     requirements={"periodeDebut"="\d{8}"}
     * )
     * Prépare le listing des services exploitants ayant saisis et validé la publication de la météo pour la période.
     */
    public function listingValidationsPublication(Request $request): JsonResponse
    {
        // On récupère l'entity manager et la date de début de période
        $resultats = [];
        $em = $this->getDoctrine()->getManager();
        $periodeDebut = \DateTime::createFromFormat('Ymd H:i:s', $request->get('periodeDebut') . ' 00:00:00');
        $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTime(23, 59, 59);

        // On récupère la liste des exploitants
        $exploitants = $em->getRepository(Service::class)->listeServicesExploitantsMeteo();

        // On récupère les validations effectuées par les exploitants dans la période
        $queryValidations = $em->getRepository(Validation::class)->validationsDateParPeriode($periodeDebut);
        $validations = [];
        foreach ($queryValidations as $validation) {
            $validations[$validation->getExploitant()->getId()] = $validation->getAjouteLe();
        }

        // On récupère les évènements saisies par les exploitants dans la période
        $queryEvenements = $em->getRepository(Evenement::class)->listeEvenementsPeriode($periodeDebut, $periodeFin);
        $exploitantsQuiOntSaisi = [];
        foreach ($queryEvenements as $evenement) {
            $exploitantsQuiOntSaisi[] = $evenement->getSaisiePar()->getId();
        }

        // On met en forme notre tableau de résultats
        foreach ($exploitants as $exploitant) {
            $resultats[] = [
                'id' => $exploitant->getId(),
                'label' => $exploitant->getLabel(),
                'meteo_saisie' => in_array($exploitant->getId(), $exploitantsQuiOntSaisi),
                'meteo_validation' => isset($validations[$exploitant->getId()]) ? $validations[$exploitant->getId()]->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('c') : false,
                'href' => $this->generateUrl('meteo-saisie-index', ['exploitant' => $exploitant->getId(), 'debutPeriode' => $request->get('periodeDebut')])
            ];
        }

        // On retourne notre résultat au format json
        return JsonResponse::create($resultats);
    }
}
