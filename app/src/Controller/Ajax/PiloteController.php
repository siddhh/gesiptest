<?php

namespace App\Controller\Ajax;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Pilote;
use App\Utils\Pagination;
use Symfony\Component\HttpFoundation\Request;

class PiloteController extends AbstractController
{

    /**
     * @Route(
     *      "/ajax/pilotes/listing/{page?1}",
     *      methods={"GET"},
     *      name="ajax-pilote-listing",
     *      requirements={"page"="\d+"}
     * )
     */
    public function listingPilotes(Request $request, int $page = 1): JsonResponse
    {
        $filtre = $request->get('filtre');

        $query = $this->getDoctrine()
            ->getRepository(Pilote::class)
            ->listePilotesFiltre($filtre);

        $pagination = new Pagination($query, $page);

        return new JsonResponse($pagination->traitement());
    }
}
