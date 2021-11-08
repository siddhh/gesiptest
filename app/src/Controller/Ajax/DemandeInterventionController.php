<?php

namespace App\Controller\Ajax;

use App\Entity\Composant\Annuaire;
use App\Entity\Demande\HistoriqueStatus;
use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DemandeInterventionController extends AbstractController
{

    /**
     * @Route("/ajax/demandes/rechercher/by-periode", methods={"GET"}, name="ajax-demandes-rechercher-byperiod")
     *  Attention ce webservice travaille avec des dates respectant le format utilisé coté client (timezone Paris - d/m/Y H:i)
     */
    public function rechercheDemandeIntervention(Request $request, UrlGeneratorInterface $router): JsonResponse
    {
        $response = [];
        $dtzFr = new \DateTimeZone('Europe/Paris');
        $dtzUtc = new \DateTimeZone('utc');
        $dateFormat = 'd/m/Y H:i';
        $dtUtcStart = \DateTime::createFromFormat($dateFormat, $request->get('start'), $dtzFr);
        $dtUtcEnd = \DateTime::createFromFormat($dateFormat, $request->get('end'), $dtzFr);
        $excludeDemandeId = $request->get('excludeId');
        if (!$dtUtcStart) {
            $response['errors'][] = 'Date de début invalide!';
        } elseif (!$dtUtcEnd) {
            $response['errors'][] = 'Date de fin invalide!';
        } elseif ($dtUtcStart > $dtUtcEnd) {
            $response['errors'][] = 'La date de fin ne peut pas être antérieure à la date de début.';
        } else {
            $response['data'] = [];
            $demandes = $this->getDoctrine()
                ->getRepository(DemandeIntervention::class)
                ->listeDemandeInterventionParPeriode($dtUtcStart->setTimezone($dtzUtc), $dtUtcEnd->setTimezone($dtzUtc));
            foreach ($demandes as $demande) {
                if ($demande->getId() != $excludeDemandeId) {
                    $row['id'] = $demande->getId();
                    $row['numero'] = $demande->getNumero();
                    $row['showDemandeLink'] = $router->generate('demandes-visualisation', ['id' => $demande->getId()]);
                    $row['demandeLe'] = $demande->getDemandeLe()->setTimezone($dtzFr)->format($dateFormat);
                    $row['dateDebut'] = $demande->getDateDebut()->setTimezone($dtzFr)->format($dateFormat);
                    $row['dateFinMax'] = $demande->getDateFinMax()->setTimezone($dtzFr)->format($dateFormat);
                    $row['status'] = $demande->getStatusLibelle();
                    $row['demandePar'] = $demande->getDemandePar()->getLabel();
                    $row['nature'] = $demande->getNatureIntervention();
                    $row['composant'] = $demande->getComposant()->getLabel();
                    $exploitants = [];
                    foreach ($demande->getServices() as $annuaire) {
                        $exploitants[] = [
                            'mission' => $annuaire->getMission()->getLabel(),
                            'service' => $annuaire->getService()->getLabel(),
                        ];
                    }
                    $row['exploitants'] = $exploitants;
                    $row['motif'] = $demande->getMotifIntervention()->getLabel();
                    $row['palier'] = (bool)$demande->getPalierApplicatif();
                    $row['description'] = $demande->getDescription();
                    $response['data'][] = $row;
                }
            }
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/ajax/demandes/{id}/action", methods={"POST"}, name="ajax-demandes-action")
     */
    public function executionAction(DemandeIntervention $demande, MailerInterface $mailer, Request $request): JsonResponse
    {
        // On récupère l'entity manager
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        // On récupère les données de la requête, on supprime la clé "Action" qui ne nous servira plus par la suite
        $dataRequest = $request->request->all();
        $request->request->remove('action');

        // On supprime "App\\Workflow\\Actions\\" du nom de la classe
        $nomAction = isset($dataRequest['action']) ? str_replace('App\\Workflow\\Actions\\', null, $dataRequest['action']) : null;

        // Si l'action n'est pas renseignée ou si l'action n'existe pas dans le workflow
        $tmpAction = "App\\Workflow\\Actions\\". $nomAction;
        if ($nomAction === null || get_class_methods($tmpAction) === null) {
            return JsonResponse::create([ 'status' => 'ko', 'message' => 'Cette action n\'existe pas.' ])->setStatusCode(500);
        }

        // Si tout est ok, on exécute l'action demandée par l'utilisateur
        $mae = $demande->getMachineEtat();

        // On ajoute les dépendances nécessaire à la réalisation des actions
        $mae->setEntityManager($em);
        $mae->setMailer($mailer);

        // On essaie de faire :
        try {
            // On demande à la machine à état d'exécuter l'action demandée et nous renvoyons la réponse de l'action
            $resultat = $mae->executerAction($tmpAction, $request);
            // On enregistre les informations
            $em->flush();

            // On renvoi le résultat de l'action
            return $resultat;

        // Si une exception est levée au cours du traitement, alors on envoi le message de l'exception à l'utilisateur
        } catch (\Exception $ex) {
            return JsonResponse::create([ 'status' => 'ko', 'message' => $ex->getMessage() ])->setStatusCode(500);
        }
    }

    /**
     * @Route("/ajax/demandes/{id}/consultations", methods={"GET"}, name="ajax-demandes-consultations")
     */
    public function suiviConsultations(DemandeIntervention $demande): JsonResponse
    {
        // On initialise se qui sera notre réponse
        $reponse = ['CDB' => [], 'services' => []];

        // On récupère l'historique complet de la demande et on le remet dans l ordre chronologique
        $historiqueStatusInverse = $demande->getHistoriqueStatus()->toArray();
        $historiqueStatus = array_reverse($historiqueStatusInverse, true);

        // On parcours l'historique de la demande
        $nombreConsultation = 0;
        /** @var HistoriqueStatus $historique */
        foreach ($historiqueStatus as $historique) {
            // Si l'état est "Consultation en cours"
            if ($historique->getStatus() == EtatConsultationEnCours::class) {
                $nombreConsultation++;
                $donneesConsultation = $historique->getDonnees();
                // Valeurs affichées par défaut pour les services de l'annuaire
                if (isset($donneesConsultation['annuaires'])) {
                    $annuaires = $this->getDoctrine()->getRepository(Annuaire::class)->findBy(['id' => $donneesConsultation['annuaires']]);
                    foreach ($annuaires as $annuaire) {
                        $reponse['services'][$annuaire->getService()->getId()] = [
                            'serviceId' => $annuaire->getService()->getId(),
                            'serviceLabel' => '',
                            'avis' => '',
                            'commentaire' => '',
                            'date' => '',
                            'nbConsultation' => $nombreConsultation
                        ];
                    }
                }
                // Valeurs affichées correspondant aux saisies
                if (isset($donneesConsultation['avis'])) {
                    foreach ($donneesConsultation['avis'] as $serviceId => $avis) {
                        $reponse['services'][$serviceId] = [
                            'serviceId' => $serviceId,
                            'serviceLabel' => '',
                            'avis' => $avis['avis'],
                            'commentaire' => htmlentities($avis['commentaire']),
                            'date' => (new \DateTime($avis['date'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('c'),
                            'nbConsultation' => $nombreConsultation
                        ];
                    }
                }
            }
            // Si l'état est "Consultation en cours du Cdb"
            if ($historique->getStatus() == EtatConsultationEnCoursCdb::class) {
                $donneesConsultation = $historique->getDonnees();
                if (isset($donneesConsultation['CDB'])) {
                    $reponse['CDB'][$donneesConsultation['CDB']['serviceId']] = [
                        'serviceId' => $donneesConsultation['CDB']['serviceId'],
                        'serviceLabel' => '',
                        'avis' => $donneesConsultation['CDB']['avis'],
                        'commentaire' => htmlentities($donneesConsultation['CDB']['commentaire']),
                        'dateAvis' => (new \DateTime($donneesConsultation['CDB']['date'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('c'),
                        'dateConsultation' => $historique->getAjouteLe()->format('c')
                    ];
                } else {
                    $reponse['CDB'][0] = [
                        'serviceId' => 0,
                        'serviceLabel' => '',
                        'avis' => '',
                        'commentaire' => '',
                        'dateAvis' => null,
                        'dateConsultation' => $historique->getAjouteLe()->format('c')
                    ];
                }
            }
        }
        // On récupère les services identifiés dans l'historique, que l'on ajoute dans notre réponse
        $idsServices = array_merge(array_keys($reponse['services']), array_keys($reponse['CDB']));
        $services = $this->getDoctrine()->getRepository(Service::class)->findBy(['id' => $idsServices]);
        /** @var Service $service */
        foreach ($services as $service) {
            if (isset($reponse['services'][$service->getId()])) {
                $reponse['services'][$service->getId()]['serviceLabel'] = $service->getLabel();
            }

            if (isset($reponse['CDB'][$service->getId()])) {
                $reponse['CDB'][$service->getId()]['serviceLabel'] = $service->getLabel();
            }
        }
        // On tri notre réponse par rapport au label des services (ASC)
        usort($reponse['services'], function ($a, $b) {
            return strcmp($a['serviceLabel'], $b['serviceLabel']);
        });

        // On retourne notre réponse au format JSON
        return JsonResponse::create($reponse);
    }
}
