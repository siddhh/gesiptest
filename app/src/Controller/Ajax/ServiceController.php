<?php

namespace App\Controller\Ajax;

use App\Entity\ActionHistory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Service;
use App\Entity\Composant;
use App\Entity\Composant\Annuaire;
use App\Entity\References\Mission;
use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Utils\Pagination;
use App\Service\ServiceUtilsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ServiceController extends AbstractController
{
    /**
     * @Route("/ajax/service/motdepasse/reinitialise/{service}", methods={"POST"}, name="ajax-service-motdepasse-reinitialise")
     * Lance la procédure de réinitialisation de mot de passe
     */
    public function motdepasseReinitialise(Service $service, ServiceUtilsService $serviceUtils): JsonResponse
    {
        $retour = $serviceUtils->motdepasseReinitialise($service);
        return new JsonResponse([
            'statut'        => $retour,
            'serviceId'     => $service->getId(),
            'serviceLabel'  => $service->getLabel()
        ]);
    }

    /**
     * @Route("/ajax/service/{service}", methods={"GET"}, name="ajax-service-get")
     * Retourne les informations d'un service
     */
    public function getService(Service $service, SerializerInterface $serializer): JsonResponse
    {
        $serializedService = $serializer->serialize(
            $service,
            'json',
            ['groups' => 'basique']
        );
        return JsonResponse::fromJsonString($serializedService);
    }

    /**
     * @Route("/ajax/service", methods={"GET"}, name="ajax-service-liste")
     */
    public function listeTousServices(): JsonResponse
    {
        $resultat = $this->getDoctrine()
            ->getRepository(Service::class)
            ->listeTousServices();

        $reponse = [
            'recherche' => 'liste',
            'donnees' => []
        ];

        foreach ($resultat as $service) {
            $reponse['donnees'][] = ['id' => $service->getId(), 'label' => $service->getLabel()];
        }
        return new JsonResponse($reponse);
    }

    /**
     * @Route("/ajax/service/recherche/label", methods={"GET"}, name="ajax-service-recherche-label")
     */
    public function rechercheServiceParLabel(Request $request): JsonResponse
    {
        $recherche = $request->query->get("label");

        $resultat = $this->getDoctrine()
            ->getRepository(Service::class)
            ->rechercheServiceParLabel($recherche);

        $reponse = [
            'recherche' => $recherche,
            'donnees' => []
        ];

        foreach ($resultat as $service) {
            $reponse['donnees'][] = ['id' => $service->getId(), 'label' => $service->getLabel()];
        }
        return new JsonResponse($reponse);
    }

    /**
     * @Route(
     *      "/ajax/services/listing/{page?1}",
     *      methods={"GET"},
     *      name="ajax-service-listing",
     *      requirements={"page"="\d+"}
     * )
     */
    public function listingServices(Request $request, int $page = 1): JsonResponse
    {
        $filtre = $request->get('filtre');

        $query = $this->getDoctrine()
            ->getRepository(Service::class)
            ->listeServicesFiltre($filtre);

        $pagination = new Pagination($query, $page);

        return new JsonResponse($pagination->traitement());
    }

    /**
    * @Route("/ajax/service/composants/{service}", methods={"GET"}, name="ajax-service-get-composants")
    * Retourne la liste des composants rattachés à un service
    */
    public function composantsRattaches(Service $service, Request $request): JsonResponse
    {
        // On initialise le nécessaire
        $reponse = ['donnees' => []];

        // On récupère les composants / missions associés au service
        $listeComposants = $this->getDoctrine()->getRepository(Annuaire::class)->composantsDuService($service);

        // On met en forme la réponse
        foreach ($listeComposants as $annuaire) {
            $reponse['donnees'][] = [
                'id' => $annuaire->getId(),
                'mission' => $annuaire->getMission()->getLabel(),
                'missionId' => $annuaire->getMission()->getId(),
                'composant' => $annuaire->getComposant()->getLabel(),
                'composantId' => $annuaire->getComposant()->getId()
            ];
        }
        return new JsonResponse($reponse);
    }

    /**
    * @Route("/ajax/service/perimetre/modification", methods={"POST"}, name="ajax-service-modification-perimetre")
    * Met à jour le périmètre applicatif d'un service
    */
    public function modificationPerimetreApplicatif(Request $request, UserInterface $user, MailerInterface $mailer, UrlGeneratorInterface $router): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();

        $service = $request->request->get('service');
        $serviceTraite = $em->getRepository(Service::class)->find($service);
        $serviceGestionnaire = $em->getRepository(Service::class)->find($user->getId());

        // traitement des modifications du périmètre du service
        $email_ajouts = [];
        $email_retraits= [];
        $modificationsPerimetre = $request->request->get('perimetre');
        if ($modificationsPerimetre != null) {
            $listeAnnuaire = $em->getRepository(Annuaire::class)->findBy([
                'service'       => $service,
                'supprimeLe'    => null
            ]);
            foreach ($modificationsPerimetre as $modification) {
                if ('r' === $modification['demandeType']) {
                    foreach ($listeAnnuaire as $annuaire) {
                        if ($annuaire->getComposant()->getId() == $modification['composantId']
                            && $annuaire->getMission()->getId() == $modification['missionId']) {
                            $annuaire->setSupprimeLe(new \DateTime());

                            // On enregistre la modification dans la base
                            $actionHistory = new ActionHistory();
                            $actionHistory->setAction(ActionHistory::UPDATE);
                            $actionHistory->setActionDate(new \DateTime());
                            $actionHistory->setIp($request->getClientIp());
                            $actionHistory->setServiceId($this->getUser()->getId());
                            $actionHistory->setObjetClasse(Composant::class);
                            $actionHistory->setObjetId($annuaire->getComposant()->getId());
                            $actionHistory->setDetails([
                                'old' => [
                                    'annuaire' => [ $annuaire->getInfos() ]
                                ],
                                'new' => [ 'annuaire' => [ ] ]
                            ]);
                            $em->persist($actionHistory);
                        }
                    }
                    array_push($email_retraits, [
                        'composant'      => $modification['composantLabel'],
                        'mission'        => $modification['missionLabel'],
                        'demande'        => ($modification['demandeId'] == null ? "non" : "oui")
                    ]);
                } elseif ('a' === $modification['demandeType']) {
                    // ajout d'un nouvel élément au périmètre
                    $annuaire = new Annuaire();
                    $annuaire->setMission($em->getRepository(Mission::class)->find($modification['missionId']));
                    $annuaire->setService($em->getRepository(Service::class)->find($service));
                    $annuaire->setComposant($em->getRepository(Composant::class)->find($modification['composantId']));
                    $em->persist($annuaire);

                    // On enregistre la modification dans la base
                    $actionHistory = new ActionHistory();
                    $actionHistory->setAction(ActionHistory::UPDATE);
                    $actionHistory->setActionDate(new \DateTime());
                    $actionHistory->setIp($request->getClientIp());
                    $actionHistory->setServiceId($this->getUser()->getId());
                    $actionHistory->setObjetClasse(Composant::class);
                    $actionHistory->setObjetId($annuaire->getComposant()->getId());
                    $actionHistory->setDetails([
                        'old' => [ 'annuaire' => [ ] ],
                        'new' => [
                            'annuaire' => [ $annuaire->getInfos() ]
                        ]
                    ]);
                    $em->persist($actionHistory);

                    array_push($email_ajouts, [
                        'composant'      => $modification['composantLabel'],
                        'mission'        => $modification['missionLabel'],
                        'demande'        => ($modification['demandeId'] == null ? "non" : "oui")
                    ]);
                }
            }
        }

        // traitement des demandes du service de modification de son périmètre
        $modificationsDemandes = $request->request->get('demandes');
        if ($modificationsDemandes != null) {
            $listeDemandes = $em->getRepository(DemandePerimetreApplicatif::class)->findBy([
                'serviceDemandeur' => $service,
                'accepteLe' => null,
                'refuseLe' => null,
                'annuleLe' => null
            ]);
            foreach ($modificationsDemandes as $modification) {
                foreach ($listeDemandes as $demande) {
                    if ($demande->getId() == $modification['demandeId']) {
                        switch ($modification['bilan']) {
                            case "a":
                                $demande->setAccepteLe(new \DateTime());
                                $demande->setAcceptePar($serviceGestionnaire);
                                break;
                            case "r":
                                $demande->setRefuseLe(new \DateTime());
                                $demande->setRefusePar($serviceGestionnaire);
                                break;
                        }
                        break;
                    }
                }
            }
        }

        $em->flush();

        // envoi du mail récapitulatif
        if ((count($email_ajouts) > 0) || (count($email_retraits) > 0)) {
            $lienGesip = $this->getParameter('base_url') . $router->generate('connexion');
            $lienDme = $this->getParameter('dme_url');
            $emailMessage = (new TemplatedEmail())
                    ->from(new Address($serviceGestionnaire->getEmail(), $serviceGestionnaire->getLabel()))
                    ->to(new Address($serviceTraite->getEmail(), $serviceTraite->getLabel()))
                    ->priority(Email::PRIORITY_HIGH)
                    ->subject("[GESIP] Mise à jour Périmètre applicatif - Accord")
                    ->textTemplate('emails/modificationPerimetreApplicatif.text.twig')
                    ->htmlTemplate('emails/modificationPerimetreApplicatif.html.twig')
                    ->context([
                            'serviceLabel'                  => $serviceTraite->getLabel(),
                            'ajouts'                        => $email_ajouts,
                            'retraits'                      => $email_retraits,
                            'lienGesip'                     => $lienGesip,
                            'lienDme'                       => $lienDme
                        ]);

            $servicesPilotage = $em->getRepository(Service::class)->findBy([
                'estPilotageDme'      => true,
                'supprimeLe'          => null
            ]);
            if ($servicesPilotage != null) {
                foreach ($servicesPilotage as $servicePilotage) {
                    $emailMessage->addCc($servicePilotage->getEmail());
                }
            }

            $mailer->send($emailMessage);
        }

        return new JsonResponse([
            'status' => 'success'
        ]);
    }
}
