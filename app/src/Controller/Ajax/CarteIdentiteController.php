<?php

namespace App\Controller\Ajax;

use App\Entity\CarteIdentite;
use App\Entity\CarteIdentiteEvenement;
use App\Entity\Composant;
use App\Entity\ModeleCarteIdentite;
use App\Entity\Service;
use App\Service\CarteIdentiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CarteIdentiteController extends AbstractController
{
    /**
     * @Route("/ajax/carte-identite/historique/{carteIdentite}", methods={"GET"}, name="ajax-carte-identite-historique")
     * Retourne l'historique d'une carte d'identité
     */
    public function historiqueCarteIdentite(CarteIdentite $carteIdentite): JsonResponse
    {
        // On initialise quelques variables dont la réponse
        $tzParis = new \DateTimeZone('Europe/Paris');
        $reponse = [
            'composant' => $carteIdentite->getGenericComposant()->getLabel(),
            'historique' => []
        ];

        // On va chercher les évènements
        $evenements = $this->getDoctrine()->getRepository(CarteIdentiteEvenement::class)->historiqueCompletDe($carteIdentite);

        // On met en forme les évènements dans notre réponse
        foreach ($evenements as $evenement) {
            array_push($reponse['historique'], [
                'horodatage'    => $evenement->getMajLe()->setTimezone($tzParis)->format('d/m/YH:i:s'),
                'service'       => $evenement->getService() ? $evenement->getService()->getLabel() : 'Non communiqué',
                'libelle'       => $evenement->getEvenement(),
                'commentaire'   => $evenement->getCommentaire(),
            ]);
        }

        // On renvoie la réponse au format json
        return new JsonResponse($reponse);
    }

    /**
     * @Route("/ajax/carte-identite/transmission/{carteIdentite}", methods={"POST"}, name="ajax-carte-identite-transmission")
     * Transmet une carte d'identite à des services tiers
     */
    public function transmissionCarteIdentite(CarteIdentite $carteIdentite, CarteIdentiteService $carteIdentiteService, Request $request): JsonResponse
    {
        // initialisations
        $serviceConnecte = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        // traitement de la requête
        $composant = $carteIdentite->getComposant();
        $destinataires = $request->request->get('destinataires');
        try {
            $message = null;
            if (!is_array($destinataires) || count($destinataires) == 0) {
                $message = "Aucun destinataire fourni";
                throw new BadRequestHttpException();
            }
            $servicesDestinataires = [];
            foreach (CarteIdentiteService::getTransmissionServiceLabels() as $serviceLabel) {
                if (in_array($serviceLabel, $destinataires)) {
                    if (($service = $em->getRepository(Service::class)->findOneBy(['label' => $serviceLabel])) == null) {
                        $message = "BALF {$serviceLabel} non trouvée";
                        throw new UnprocessableEntityHttpException();
                    } else {
                        $servicesDestinataires[] = $service;
                    }
                    $setTransmissionMethode = 'setTransmission' . str_replace(' ', '', ucwords(trim($serviceLabel)));
                    $carteIdentite->{$setTransmissionMethode}(true);
                    $evenement = new CarteIdentiteEvenement();
                    $evenement->setService($serviceConnecte);
                    $evenement->setEvenement("Transmission à {$serviceLabel}");
                    $evenement->setCarteIdentite($carteIdentite);
                    if ($carteIdentite->getComposant()) {
                        $evenement->setComposant($carteIdentite->getComposant());
                    } elseif ($carteIdentite->getComposantCarteIdentite()) {
                        $evenement->setComposantCarteIdentite($carteIdentite->getComposantCarteIdentite());
                    }
                    $em->persist($evenement);
                }
            }

            // envoi du mail
            $message = "échec envoi du courriel";
            if (!empty($servicesDestinataires)) {
                $carteIdentiteService->envoyerMail($carteIdentite, $servicesDestinataires);
            }

            // écriture en BDD
            $message = "échec écriture en base de données";
            $em->flush();
        } catch (\Throwable $ex) {
            return new JsonResponse([
                'statut'   => 'ko',
                'message' => $message
            ]);
        }

        $this->addFlash('success', "Votre demande a été transmise avec succès au(x) service(s) : ". implode(', ', $destinataires) . ".");

        return new JsonResponse(['statut' => 'ok']);
    }

    /**
     * Retourne la liste des composants n'ayant pas encore de carte d'identité triée par libellé
     * @Route("/ajax/carte-identite/recherche/label", methods={"GET"}, name="ajax-carte-identite-recherche-label")
     */
    public function rechercheComposantParLabel(Request $request): JsonResponse
    {
        $labelSearch = '%' . str_replace(['_', '%'], ['\\_', '\\%'], $request->query->get('label')) . '%';
        $composants = $this->getDoctrine()->getRepository(Composant::class)
            ->createQueryBuilder('co')
            ->leftJoin('co.carteIdentites', 'ca')
            ->where('co.archiveLe IS NULL')
            ->andWhere('UPPER(co.label) LIKE UPPER(:labelSearch)')
            ->setParameter('labelSearch', $labelSearch)
            ->orderBy('UPPER(co.label)', 'ASC')
            ->getQuery()
            ->getResult();
        $response = [];
        foreach ($composants as $composant) {
            $response[] = [
                'id'    => $composant->getId(),
                'label' => $composant->getLabel(),
                'carte' => (count($composant->getCarteIdentites()) == 0 ? 'non' : 'oui'),
            ];
        }
        return new JsonResponse($response);
    }

    /**
     * Permet d'activer un modèle de carte d'identité (statut 204 retourné en cas de succès)
     * @Route("/ajax/modele-carte-identite/activer/{modeleCarteIdentite}", methods={"PUT"}, name="ajax-modele-carte-identite-activer")
     * @param ModeleCarteIdentite $modelCarteIdentite
     * @return Response
     */
    public function modeleCarteIdentiteActiver(ModeleCarteIdentite $modeleCarteIdentite): Response
    {
        $this->getDoctrine()->getRepository(ModeleCarteIdentite::class)->activeModele($modeleCarteIdentite);
        $response = new Response();
        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        return $response;
    }

    /**
     * Supprime le modèle de carte d'identité (statut 204 retourné en cas de succès)
     * @Route("/ajax/modele-carte-identite/{modeleCarteIdentite}", methods={"DELETE"}, name="ajax-modele-carte-identite-supprimer")
     * @param ModeleCarteIdentite $modelCarteIdentite
     * @return Response
     */
    public function modeleCarteIdentiteSupprimer(ModeleCarteIdentite $modeleCarteIdentite, CarteIdentiteService $carteIdentiteService): Response
    {
        // Suppression du fichier
        $carteIdentiteService->supprime($modeleCarteIdentite);
        // Suppression de l'entrée en base de données
        $em = $this->getDoctrine()->getManager();
        $em->remove($modeleCarteIdentite);
        $em->flush();
        $response = new Response();
        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        return $response;
    }
}
