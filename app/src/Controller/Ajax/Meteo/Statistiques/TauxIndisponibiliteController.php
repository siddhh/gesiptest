<?php

namespace App\Controller\Ajax\Meteo\Statistiques;

use App\Entity\Composant;
use App\Form\Meteo\Statistiques\TauxIndisponibilitesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TauxIndisponibiliteController extends AbstractController
{
    /**
     * @Route("/ajax/meteo/statistiques/taux-indisponibilites", name="ajax-pourcentage-taux-indisponibilites")
     */
    public function index(Request $request): JsonResponse
    {
        // On initialise quelques variables
        $reponse = [];
        $form = $this->createForm(TauxIndisponibilitesType::class);
        $form->submit($request->request->get($form->getName()));
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération des données formulaire
            $data = $form->getData();
            $source = $data['moduleSource'];
            $anneeDebut = $data['periodeDebut'];
            $anneeFin = $data['periodeFin'];
            $frequence = $data['frequence'];

            // Récupère la liste des composants correspondants
            $composantRepository = $this->getDoctrine()->getManager()->getRepository(Composant::class);
            $composants = $composantRepository->getComposantIndisponibilites($data);

            // Pour chaque période on récupère la météo des composants concernés
            $periodeDebut = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anneeDebut . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));
            $periodeFin = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $anneeFin + 1 . '-01-01 00:00:00', new \DateTimeZone('Europe/Paris'));
            $reponseData = [
                'periode' => [
                    'label' => 'Du ' . $periodeDebut->format('d/m/Y') . ' au ' . $periodeFin->format('d/m/Y'),
                    'debut' => $periodeDebut->format('d/m/Y H:i:s'),
                    'fin'   => $periodeFin->format('d/m/Y H:i:s'),
                ],
                'frequence'         => $frequence,
                'composants'        => [],
                'subperiodes'       => [],
            ];

            // Injecte dans la réponse la liste des composants fitlrés
            foreach ($composants as $composant) {
                $reponseData['composants'][] = [
                    'id'    => $composant->getId(),
                    'label' => $composant->getLabel()
                ];
            }

            // Pour chaque période on récupère le taux de disponibilité
            $subPeriodeDebut = clone($periodeDebut);
            while ($subPeriodeDebut->getTimestamp() < $periodeFin->getTimestamp()) {
                // On calcule la fin de la période courante
                $subPeriodeFin = $subPeriodeDebut->add(new \DateInterval($frequence))->sub(new \DateInterval('PT1S'));
                // On récupère les taux d'indisponibilités pour la période courante
                $reponseData['subperiodes'][] = $composantRepository->getTauxIndisponibilites(
                    $source,
                    \DateTime::createFromImmutable($subPeriodeDebut),
                    \DateTime::createFromImmutable($subPeriodeFin),
                    $composants
                );
                // Increment, pour passer à la période suivante
                $subPeriodeDebut = $subPeriodeDebut->add(new \DateInterval($frequence));
            }

            // Ajoute les données collectées à la réponse
            $reponse = ['data' => $reponseData];
        } else {
            // formulaire non valide ou non soumis correctement
            return $this->retourneReponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                false,
                self::getErreurMessages($form)
            );
        }

        // On renvoie la météo
        return JsonResponse::create($reponse);
    }

    /**
     * Méthode helper générant une réponse standardisée
     */
    private function retourneReponse(int $httpStatus, bool $success, array $errors = [], array $optionalData = []): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'errors' => $errors,
            'data' => array_merge(
                $optionalData
            )
        ], $httpStatus);
    }

    /**
     * Méthode permettant de retourner les messages d'erreurs à partir d un formulaire
     */
    private static function getErreurMessages(Form $form): array
    {
        $erreurMessages = [];
        foreach ($form->getErrors(true) as $formError) {
            $erreurMessages[] = $formError->getMessage();
        }
        return $erreurMessages;
    }
}
