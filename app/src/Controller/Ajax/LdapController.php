<?php

namespace App\Controller\Ajax;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\LdapService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LdapController extends AbstractController
{
    /**
     * @Route("/ajax/ldap/recherche/structures", methods={"GET"}, name="ajax-ldap-recherche-structures")
     */
    public function ldapRechercheStructures(LdapService $ldapService, Request $request): JsonResponse
    {
        $recherche = $request->query->get("recherche");
        $resultat = [
            'recherche' => $recherche,
            'donnees' => []
        ];

        if (strlen($recherche) >= 3) {
            $resultat['donnees'] = $ldapService->rechercheStructures($recherche);
        }

        return new JsonResponse($resultat);
    }


    /**
     * @Route("/ajax/ldap/recherche/personnes", methods={"GET"}, name="ajax-ldap-recherche-personnes")
     */
    public function ldapRecherchePersonnes(LdapService $ldapService, Request $request): JsonResponse
    {
        $recherche = $request->query->get("recherche");
        $resultat = [
            'recherche' => $recherche,
            'donnees' => []
        ];

        if (strlen($recherche) >= 3) {
            $resultat['donnees'] = $ldapService->recherchePersonnes($recherche);
        }

        return new JsonResponse($resultat);
    }
}
