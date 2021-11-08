<?php

namespace App\Controller\Ajax\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\Service;
use App\Repository\ComposantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ReferentielFluxController extends AbstractController
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
     * @Route(
     *      "/ajax/fiabilisation/flux/entrants/{id}",
     *      methods={"POST"},
     *      name="ajax-fiabilisation-flux-entrants-post",
     *     requirements={"id"="\d+"}
     * )
     */
    public function postFluxEntrants(Request $request, Composant $composant): JsonResponse
    {
        // On récupère l'entity manager et le service courant
        $em = $this->getDoctrine()->getManager();

        // On vérifie que le service courant à la possibilité d'ajouter une demande pour ce composant
        $this->verificationAccessComposant($composant);

        // On récupère les informations de la requête
        $ajouts = $request->get('ajouts', []);
        $retraits = $request->get('retraits', []);


        // Si le service n'est pas un pilote et un admin, on crée les demandes
        if (!$this->serviceCourant->getEstPilotageDme() && !in_array(Service::ROLE_ADMIN, $this->serviceCourant->getRoles())) {
            // On récupère les demandes en cours
            $demandesEnAttente = $this->getDoctrine()->getRepository(DemandeReferentielFlux::class)
                ->findAllDemandes($this->serviceCourant, $composant);

            // On parcourt les demandes déjà en cours, et on les annule si le service demandeur ne souhaite plus les réaliser
            /** @var DemandeReferentielFlux $demandes */
            foreach ($demandesEnAttente as $demandes) {
                // On sauvegarde l'id du composant target
                $composantTargetId = $demandes->getComposantTarget()->getId();

                // Si la demande est en mode Ajout
                if ($demandes->getType() === DemandeReferentielFlux::AJOUT) {
                    // Si la demande n'est pas dans la liste des ajouts à garder
                    if (!in_array($composantTargetId, $ajouts)) {
                        // On annule la demande
                        $demandes->annuler($this->serviceCourant);
                    } else {
                        // On supprime la valeur dans le tableau d'ajouts, car déjà en base
                        $ajouts = array_merge(array_diff($ajouts, [$composantTargetId]));
                    }

                    // Si la demande est en mode Retrait
                } elseif ($demandes->getType() === DemandeReferentielFlux::RETRAIT) {
                    // Si la demande n'est pas dans la liste des retraits à garder
                    if (!in_array($composantTargetId, $retraits)) {
                        // On annule la demande
                        $demandes->annuler($this->serviceCourant);
                    } else {
                        // On supprime la valeur dans le tableau des retraits, car déjà en base
                        $retraits = array_merge(array_diff($retraits, [$composantTargetId]));
                    }
                }
            }

            // Si il reste encore des données à traiter dans les tableaux ajouts et retraits
            if (count($ajouts) > 0 || count($retraits) > 0) {
                // On récupère tous les composants pour plus tard ...
                /** @var ComposantRepository $composantRepository */
                $composantRepository = $em->getRepository(Composant::class);
                $composants = $composantRepository->createQueryBuilder('c')
                    ->indexBy('c', 'c.id')
                    ->where('c.id IN (:ajouts)')
                    ->setParameter('ajouts', $ajouts)
                    ->orWhere('c.id IN (:retraits)')
                    ->setParameter('retraits', $retraits)
                    ->getQuery()
                    ->getResult();

                // On met en place les nouvelles demandes en ajouts
                foreach ($ajouts as $a) {
                    // On vérifie que le composant existe bien et qu'on agit pas sur le même composant, sinon on ne fait rien
                    if (isset($composants[$a]) && $a != $composant->getId()) {
                        // On crée notre nouvelle demande
                        $nouvelleDemande = new DemandeReferentielFlux();
                        $nouvelleDemande->setServiceDemandeur($this->serviceCourant);
                        $nouvelleDemande->setType(DemandeReferentielFlux::AJOUT);
                        $nouvelleDemande->setComposantSource($composant);
                        $nouvelleDemande->setComposantTarget($composants[$a]);
                        // que l'on persiste en base de données
                        $em->persist($nouvelleDemande);
                    }
                }

                // On met en place les nouvelles demandes en retraits
                foreach ($retraits as $a) {
                    // On vérifie que le composant existe bien et qu'on agit pas sur le même composant, sinon on ne fait rien
                    if (isset($composants[$a]) && $a != $composant->getId()) {
                        // On crée notre nouvelle demande
                        $nouvelleDemande = new DemandeReferentielFlux();
                        $nouvelleDemande->setServiceDemandeur($this->serviceCourant);
                        $nouvelleDemande->setType(DemandeReferentielFlux::RETRAIT);
                        $nouvelleDemande->setComposantSource($composant);
                        $nouvelleDemande->setComposantTarget($composants[$a]);
                        // que l'on persiste en base de données
                        $em->persist($nouvelleDemande);
                    }
                }
            }

            // On ajoute un message flash
            $this->addFlash(
                'success',
                'Vos demandes ont bien été prises en compte et seront traitées prochainement.'
            );

        // Si le service courant est un DME ou ADMIN on ne fait pas de demandes mais on passe en direct
        } else {
            // On récupère les composants à ajouter / retirer
            $composantsAAjouter = $em->getRepository(Composant::class)->findBy(['id' => $ajouts]);
            $composantsARetirer = $em->getRepository(Composant::class)->findBy(['id' => $retraits]);

            // On ajoute les composants
            /** @var Composant $c */
            foreach ($composantsAAjouter as $c) {
                $composant->addImpactesParComposant($c);
            }

            // On retirer les composants
            /** @var Composant $c */
            foreach ($composantsARetirer as $c) {
                $composant->removeImpactesParComposant($c);
            }

            // On ajoute un message flash
            $this->addFlash(
                'success',
                'Vos modifications ont bien été prises en compte.'
            );
        }

        // On tire la chasse!
        $em->flush();

        // On notifie que tout est ok !
        return new JsonResponse([
            'status' => 'success'
        ]);
    }

    /**
     * @Route(
     *      "/ajax/fiabilisation/flux/sortants/{id}",
     *      methods={"POST"},
     *      name="ajax-fiabilisation-flux-sortants-post",
     *     requirements={"id"="\d+"}
     * )
     */
    public function postFluxSortants(Request $request, Composant $composant): JsonResponse
    {
        // On récupère l'entity manager
        $em = $this->getDoctrine()->getManager();

        // On vérifie que le service courant à la possibilité d'ajouter une demande pour ce composant
        $this->verificationAccessComposant($composant);

        // On récupère les informations de la requête
        $ajouts = $request->get('ajouts', []);
        $retraits = $request->get('retraits', []);

        // Si le service n'est pas un pilote et un admin, on crée les demandes
        if (!$this->serviceCourant->getEstPilotageDme() && !in_array(Service::ROLE_ADMIN, $this->serviceCourant->getRoles())) {
            // On récupère les demandes en cours
            $demandesEnAttente = $this->getDoctrine()->getRepository(DemandeReferentielFlux::class)
                ->findAllDemandes($this->serviceCourant, null, $composant);

            // On parcourt les demandes déjà en cours, et on les annule si le service demandeur ne souhaite plus les réaliser
            /** @var DemandeReferentielFlux $demandes */
            foreach ($demandesEnAttente as $demandes) {
                // On sauvegarde l'id du composant source
                $composantSourceId = $demandes->getComposantSource()->getId();

                // Si la demande est en mode Ajout
                if ($demandes->getType() === DemandeReferentielFlux::AJOUT) {
                    // Si la demande n'est pas dans la liste des ajouts à garder
                    if (!in_array($composantSourceId, $ajouts)) {
                        // On annule la demande
                        $demandes->annuler($this->serviceCourant);
                    } else {
                        // On supprime la valeur dans le tableau d'ajouts, car déjà en base
                        $ajouts = array_merge(array_diff($ajouts, [$composantSourceId]));
                    }

                    // Si la demande est en mode Retrait
                } elseif ($demandes->getType() === DemandeReferentielFlux::RETRAIT) {
                    // Si la demande n'est pas dans la liste des retraits à garder
                    if (!in_array($composantSourceId, $retraits)) {
                        // On annule la demande
                        $demandes->annuler($this->serviceCourant);
                    } else {
                        // On supprime la valeur dans le tableau des retraits, car déjà en base
                        $retraits = array_merge(array_diff($retraits, [$composantSourceId]));
                    }
                }
            }

            // Si il reste encore des données à traiter dans les tableaux ajouts et retraits
            if (count($ajouts) > 0 || count($retraits) > 0) {
                // On récupère tous les composants pour plus tard ...
                /** @var ComposantRepository $composantRepository */
                $composantRepository = $em->getRepository(Composant::class);
                $composants = $composantRepository->createQueryBuilder('c')
                    ->indexBy('c', 'c.id')
                    ->where('c.id IN (:ajouts)')
                    ->setParameter('ajouts', $ajouts)
                    ->orWhere('c.id IN (:retraits)')
                    ->setParameter('retraits', $retraits)
                    ->getQuery()
                    ->getResult();

                // On met en place les nouvelles demandes en ajouts
                foreach ($ajouts as $a) {
                    // On vérifie que le composant existe bien et qu'on agit pas sur le même composant, sinon on ne fait rien
                    if (isset($composants[$a]) && $a != $composant->getId()) {
                        // On crée notre nouvelle demande
                        $nouvelleDemande = new DemandeReferentielFlux();
                        $nouvelleDemande->setServiceDemandeur($this->serviceCourant);
                        $nouvelleDemande->setType(DemandeReferentielFlux::AJOUT);
                        $nouvelleDemande->setComposantSource($composants[$a]);
                        $nouvelleDemande->setComposantTarget($composant);
                        // que l'on persiste en base de données
                        $em->persist($nouvelleDemande);
                    }
                }

                // On met en place les nouvelles demandes en retraits
                foreach ($retraits as $a) {
                    // On vérifie que le composant existe bien et qu'on agit pas sur le même composant, sinon on ne fait rien
                    if (isset($composants[$a]) && $a != $composant->getId()) {
                        // On crée notre nouvelle demande
                        $nouvelleDemande = new DemandeReferentielFlux();
                        $nouvelleDemande->setServiceDemandeur($this->serviceCourant);
                        $nouvelleDemande->setType(DemandeReferentielFlux::RETRAIT);
                        $nouvelleDemande->setComposantSource($composants[$a]);
                        $nouvelleDemande->setComposantTarget($composant);
                        // que l'on persiste en base de données
                        $em->persist($nouvelleDemande);
                    }
                }
            }

            // On ajoute un message
            $this->addFlash(
                'success',
                'Vos demandes ont bien été prises en compte et seront traités prochainement.'
            );

        // Si le service courant est un DME ou ADMIN on ne fait pas de demandes mais on passe en direct
        } else {
            // On récupère les composants à ajouter / retirer
            $composantsAAjouter = $em->getRepository(Composant::class)->findBy(['id' => $ajouts]);
            $composantsARetirer = $em->getRepository(Composant::class)->findBy(['id' => $retraits]);

            // On ajoute les composants
            /** @var Composant $c */
            foreach ($composantsAAjouter as $c) {
                $composant->addComposantsImpacte($c);
            }

            // On retirer les composants
            /** @var Composant $c */
            foreach ($composantsARetirer as $c) {
                $composant->removeComposantsImpacte($c);
            }

            // On ajoute un message flash
            $this->addFlash(
                'success',
                'Vos modifications ont bien été prises en compte.'
            );
        }

        // On tire la chasse (d'eau)!
        $em->flush();

        // On notifie que tout est ok !
        return new JsonResponse([
            'status' => 'success'
        ]);
    }

   /**
     * @Route(
     *      "/ajax/fiabilisation/flux/demandes/{action}",
     *      methods={"PUT"},
     *      name="ajax-fiabilisation-demandes-flux-action",
     *      requirements={"action"="accept|refuse|cancel"}
     * )
     */
    public function updateDemandesFlux(string $action, Request $request): JsonResponse
    {
        //
        $em = $this->getDoctrine()->getManager();
        // récupération des paramètres
        $demandeIds = $request->get('demandeIds', []);
        $comment = $request->get('comment');
        $labelAction = $action == 'accept' ? 'Accord' : 'Refus';
        if (count($demandeIds) > 0) {
            // On récupère les demandes selectionnées à partir de la base de données
            $demandesFlux = $em->getRepository(DemandeReferentielFlux::class)
               ->createQueryBuilder('d')
               ->addSelect(['cs', 'ct', 'cse', 'csp', 'cte', 'ctp'])
               ->join('d.composantSource', 'cs')
               ->join('d.composantTarget', 'ct')
               ->leftJoin('cs.equipe', 'cse')
               ->leftJoin('cs.pilote', 'csp')
               ->leftJoin('ct.equipe', 'cte')
               ->leftJoin('ct.pilote', 'ctp')
               ->where('d.id IN (:demandeIds)')
               ->setParameter('demandeIds', $demandeIds)
               ->orderBy('d.serviceDemandeur', 'ASC')
               ->getQuery()
               ->getResult();

            // Tant qu'on a des demandes à traiter...
            $demandeFlux = reset($demandesFlux);
            while ($demandeFlux !== false) {
                // Tant que la demande courante concerne le meme service demandeur...
                $serviceDemandeur = $demandeFlux->getServiceDemandeur();
                $demandesByServiceDemandeur = [];
                $copyAdresses = [];
                while ($demandeFlux !== false && $serviceDemandeur == $demandeFlux->getServiceDemandeur()) {
                    if ($demandeFlux->estEnAttente()) {
                        // on effectue les modifications demandées (statut et commentaire)
                        switch ($action) {
                            case 'accept':
                                $demandeFlux->accepter($this->serviceCourant);
                                $demandeFlux->appliquer();
                                break;
                            case 'refuse':
                                $demandeFlux->refuser($this->serviceCourant);
                                break;
                            case 'cancel':
                                $demandeFlux->annuler($this->serviceCourant);
                                break;
                            default:
                                throw new \Exception('Action inconnue.');
                        }
                        $demandeFlux->setCommentaire($comment);
                        // On ajoute cette demande à la liste des demandes du service demandeur et on ajoute une adresse en copie si besoin
                        $demandesByServiceDemandeur[] = $demandeFlux;
                        $sourceEquipe = $demandeFlux->getComposantSource()->getEquipe();
                        $targetEquipe = $demandeFlux->getComposantTarget()->getEquipe();
                        if ($sourceEquipe instanceof Service) {
                            $copyAdresses[$sourceEquipe->getEmail()] = new Address($sourceEquipe->getEmail(), $sourceEquipe->getLabel());
                        }
                        if ($targetEquipe instanceof Service) {
                            $copyAdresses[$targetEquipe->getEmail()] = new Address($targetEquipe->getEmail(), $targetEquipe->getLabel());
                        }
                    }
                    // On récupère la suivante
                    $demandeFlux = next($demandesFlux);
                }
                // envoi d'un mail par service demandeur
                $emailMessage = (new TemplatedEmail())
                   ->from(new Address($this->serviceCourant->getEmail(), $this->serviceCourant->getLabel()))
                   ->to(new Address($serviceDemandeur->getEmail(), $serviceDemandeur->getLabel()))
                   ->priority(Email::PRIORITY_HIGH)
                   ->subject("[GESIP] - Mise à jour Référentiel des flux - {$labelAction}")
                   ->textTemplate('emails/fiabilisation/flux/demandes-modification.text.twig')
                   ->htmlTemplate('emails/fiabilisation/flux/demandes-modification.html.twig')
                   ->context([
                       'labelAction'   => $labelAction,
                       'actionType'    => $action,
                       'demandesFlux'  => $demandesByServiceDemandeur,
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
}
