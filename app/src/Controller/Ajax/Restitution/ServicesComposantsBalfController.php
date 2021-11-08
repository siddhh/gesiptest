<?php

namespace App\Controller\Ajax\Restitution;

use App\Entity\ActionHistory;
use App\Entity\Composant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Service;
use App\Entity\Composant\Annuaire;
use Symfony\Component\HttpFoundation\Request;

class ServicesComposantsBalfController extends AbstractController
{
    /**
     * @Route(
     *     "/ajax/restitution/services-composants-balfs/modification",
     *     methods={"POST"},
     *     name="ajax-restitution-services-composants-balf-modification"
     * )
     *
     * @param Request                $request
     * @param EntityManagerInterface $em
     *
     * @return JsonResponse
     */
    public function modificationBalfs(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // On initialise notre script
        $erreurs = ['services' => [], 'annuaires' => []];

        // On récupère les données d'entrées
        $data = json_decode($request->getContent(), true);

        // Si les données en entrée sont valides
        if ($data !== null) {
            //----- Services
            // On traite les services
            $services = $data['services'];
            $idsServices = array_column($services, 'id');
            $balfsServices = array_combine($idsServices, array_column($services, 'balf'));

            // On récupère tous les services à modifier
            $dbServices = $em->getRepository(Service::class)->findBy([ 'id' => $idsServices ]);

            // On parcourt les services à modifier et on change leurs balfs
            /** @var Service $service */
            foreach ($dbServices as $service) {
                $balf = $balfsServices[$service->getId()];
                if (filter_var($balf, FILTER_VALIDATE_EMAIL)) {
                    $service->setEmail($balf);
                } else {
                    $erreurs['services'][] = $service->getId();
                }
            }

            //----- Annuaires
            // On traite les annuaires
            $annuaires = $data['annuaires'];
            $idsAnnuaires = array_column($annuaires, 'id');
            $balfsAnnuaires = array_combine($idsAnnuaires, array_column($annuaires, 'balf'));

            // On récupère tous les annuaires à modifier
            $dbAnnuaires = $em->getRepository(Annuaire::class)->listingParIdsAvecRelations($idsAnnuaires);

            // On parcourt les annuaires à modifier et on change leurs balfs
            /** @var Annuaire $annuaire */
            foreach ($dbAnnuaires as $annuaire) {
                $balf = $balfsAnnuaires[$annuaire->getId()];
                if (filter_var($balf, FILTER_VALIDATE_EMAIL)) {
                    //---
                    // Note: Comme, l'historique n'est plus enregistrée automatiquement,
                    // nous devons créer un ActionHistory associée à la modification de la balf.
                    //---

                    // On sauvegarde l'avant modification
                    $detailHistory = ['old' => ['annuaire' => [ $annuaire->getInfos() ] ]];

                    // On change la balf
                    $annuaire->setBalf($balf);

                    // On sauvegarde l'après modification
                    $detailHistory['new'] = ['annuaire' => [ $annuaire->getInfos() ] ];

                    // Si jamais il y a eu réellement modification de la balf, alors on crée notre ActionHistory.
                    if ($detailHistory['old'] !== $detailHistory['new']) {
                        $actionHistory = new ActionHistory();
                        $actionHistory->setIp($request->getClientIp());
                        $actionHistory->setServiceId($this->getUser()->getId());
                        $actionHistory->setObjetClasse(Composant::class);
                        $actionHistory->setObjetId($annuaire->getComposant()->getId());
                        $actionHistory->setAction(ActionHistory::UPDATE);
                        $actionHistory->setDetails($detailHistory);
                        $em->persist($actionHistory);
                    }
                } else {
                    $erreurs['annuaires'][] = $annuaire->getId();
                }
            }

            // Si il y a au moins une erreur, alors on annule tout !
            if (!empty($erreurs['services']) || !empty($erreurs['annuaires'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Une ou plusieurs adresses emails sont invalides.',
                    'errors' => $erreurs
                ]);
            }

            // On tire la chasse et on félicite l'utilisateur!
            $em->flush();
            return new JsonResponse([
                'success' => true,
                'message' => 'Toutes les modifications ont été prises en compte.',
            ]);
        }

        // On engueule l'utilisateur car l'entrée n'est pas valide ! :)
        return new JsonResponse([
            'success' => false,
            'message' => 'Une erreur a été rencontrée, merci de réessayer plus tard.'
        ]);
    }
}
