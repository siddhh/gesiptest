<?php

namespace App\Controller\Ajax\Fiabilisation;

use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\Service;
use App\Entity\Composant;
use App\Entity\References\Mission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class PerimetreApplicatifController extends AbstractController
{
    /** @var Service */
    private $serviceCourant;
    /** @var MailerInterface */
    private $mailer;

    /**
     * Constructeur de ReferentielFluxController
     * @param Security $security
     * @param MailerInterface $mailer
     */
    public function __construct(Security $security, MailerInterface $mailer)
    {
        $this->serviceCourant = $security->getUser();
        $this->mailer = $mailer;
    }

    /**
     * @Route(
     *      "/ajax/fiabilisation/applicatif/demandes/{action}",
     *      methods={"PUT"},
     *      name="ajax-fiabilisation-demandes-applicatif-action",
     *      requirements={"action"="accept|refuse|cancel"}
     * )
     *
     * @param string $action
     * @param Request $request
     * @return JsonResponse
     */
    public function updateDemandes(string $action, Request $request): JsonResponse
    {
        //
        $em = $this->getDoctrine()->getManager();
        // récupération des paramètres
        $demandeIds = $request->get('demandeIds', []);
        $comment = $request->get('comment');
        $labelAction = $action == 'accept' ? 'Accord' : 'Refus';
        if (count($demandeIds) > 0) {
            // On récupère les demandes selectionnées à partir de la base de données
            $demandes = $em->getRepository(DemandePerimetreApplicatif::class)
                ->createQueryBuilder('d')
                ->addSelect(['c', 'ce', 'cp'])
                ->join('d.composant', 'c')
                ->leftJoin('c.equipe', 'ce')
                ->leftJoin('c.pilote', 'cp')
                ->where('d.id IN (:demandeIds)')
                ->setParameter('demandeIds', $demandeIds)
                ->orderBy('d.serviceDemandeur', 'ASC')
                ->getQuery()
                ->getResult();
            // Tant qu'on a des demandes à traiter...
            /** @var DemandePerimetreApplicatif $demande */
            $demande = reset($demandes);
            while ($demande !== false) {
                // Tant que la demande courante concerne le meme service demandeur...
                $serviceDemandeur = $demande->getServiceDemandeur();
                $demandesByServiceDemandeur = [];
                $copyAdresses = [];
                while ($demande !== false && $serviceDemandeur === $demande->getServiceDemandeur()) {
                    if ($demande->estEnAttente()) {
                        // on effectue les modifications demandées (statut et commentaire)
                        switch ($action) {
                            case 'accept':
                                $demande->accepter($this->serviceCourant);
                                $demande->appliquer();
                                $demande->enregistrementActionHistory($em, $request, $this->serviceCourant);
                                break;
                            case 'refuse':
                                $demande->refuser($this->serviceCourant);
                                break;
                            case 'cancel':
                                $demande->annuler($this->serviceCourant);
                                break;
                            default:
                                throw new \Exception('Action inconnue.');
                        }
                        $demande->setCommentaire($comment);
                        // On ajoute cette demande à la liste des demandes du service demandeur et on ajoute une adresse en copie si besoin
                        $demandesByServiceDemandeur[] = $demande;
                        $sourceEquipe = $demande->getComposant()->getEquipe();
                        if ($sourceEquipe != null) {
                            $copyAdresses[$sourceEquipe->getEmail()] = new Address($sourceEquipe->getEmail(), $sourceEquipe->getLabel());
                        }
                    }
                    // On récupère la suivante
                    $demande = next($demandes);
                }
                // envoi d'un mail par service demandeur
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address($this->serviceCourant->getEmail(), $this->serviceCourant->getLabel()))
                    ->to(new Address($serviceDemandeur->getEmail(), $serviceDemandeur->getLabel()))
                    ->priority(Email::PRIORITY_HIGH)
                    ->subject("[GESIP] - Mise à jour Périmètre applicatif - {$labelAction}")
                    ->textTemplate('emails/fiabilisation/applicatif/demandes-modification.text.twig')
                    ->htmlTemplate('emails/fiabilisation/applicatif/demandes-modification.html.twig')
                    ->context([
                        'labelAction'   => $labelAction,
                        'actionType'    => $action,
                        'demandes'      => $demandesByServiceDemandeur,
                        'comment'       => $comment
                    ]);
                foreach ($copyAdresses as $copyAddress) {
                    $emailMessage->cc($copyAddress);
                }
                $this->mailer->send($emailMessage);
            }
            // On "commit" les modifications en base de données.
            $em->flush();
        }
        // envoi du message de succès
        $this->addFlash(
            'success',
            "Votre {$labelAction} est pris en compte."
        );
        return new JsonResponse([
            'status' => 'success'
        ]);
    }

    /**
     * @Route(
     *      "/ajax/fiabilisation/applicatif/demandes/service/{service}",
     *      methods={"GET"},
     *      name="ajax-fiabilisation-demandes-applicatif-par_service",
     *      requirements={"service"="\d+"}
     * )
     */
    public function listePerimetreApplicatifEncoursService(Service $service): JsonResponse
    {
        $resultat = $this->getDoctrine()
            ->getRepository(DemandePerimetreApplicatif::class)
            ->listePerimetreApplicatifEncoursService($service);

        $reponse = [
            'service' => $service->getId(),
            'donnees' => []
        ];
        foreach ($resultat as $demande) {
            $reponse['donnees'][] = [
                'id' => $demande->getId(),
                'composantId' => $demande->getComposant()->getId(),
                'composantLabel' => $demande->getComposant()->getLabel(),
                'missionId' => $demande->getMission()->getId(),
                'missionLabel' => $demande->getMission()->getLabel(),
                'type' => $demande->getType()
            ];
        }
        return new JsonResponse($reponse);
    }

    /**
     * @Route(
     *      "/ajax/fiabilisation/applicatif/demandes/service/maj",
     *      methods={"POST"},
     *      name="ajax-fiabilisation-applicatif-demandes-service-maj"
     * )
     */
    public function majDemandesEncoursService(Request $request): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();

        $service = $request->request->get('service');
        $serviceTraite = $em->getRepository(Service::class)->find($service);

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
                if ($modification['demandeId'] == null) {
                    $nouvelleDemande = new DemandePerimetreApplicatif();
                    $nouvelleDemande->setServiceDemandeur($serviceTraite);
                    $nouvelleDemande->setType($modification['demandeType'] == "a" ? "add" : "remove");
                    $nouvelleDemande->setComposant($em->getRepository(Composant::class)->find($modification['composantId']));
                    $nouvelleDemande->setMission($em->getRepository(Mission::class)->find($modification['missionId']));
                    $em->persist($nouvelleDemande);
                } else {
                    foreach ($listeDemandes as $demande) {
                        if ($demande->getId() == $modification['demandeId']) {
                            $demande->annuler($serviceTraite);
                            break;
                        }
                    }
                }
            }
        }

        $em->flush();

        // envoi du message de succès
        $this->addFlash(
            'success',
            "Votre demande est transmise aux équipes de pilotage du Bureau SI-2A."
        );

        return new JsonResponse([
            'status' => 'success'
        ]);
    }
}
