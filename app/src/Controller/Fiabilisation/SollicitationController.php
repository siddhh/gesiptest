<?php

namespace App\Controller\Fiabilisation;

use App\Entity\Service;
use App\Form\Fiabilisation\MiseajourServiceEmailType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class SollicitationController extends AbstractController
{

    /** @var Service */
    private $serviceCourant;

    /**
     * Constructeur de ReferentielFluxController
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->serviceCourant = $security->getUser();
    }

    /**
     * @Route("/fiabilisation/sollicitation/miseajour/balf", name="fiabilisation-sollicitation-miseajour-balf")
     */
    public function balfUpdate(Request $request): Response
    {
        $currentBalf = $this->serviceCourant->getEmail();
        $form = $this->createForm(MiseajourServiceEmailType::class, $this->serviceCourant);
        $form->handleRequest($request);
        $validBalf = false;
        $balfUpdated = false;
        if ($form->isSubmitted()) {
            $em = $this->getDoctrine()->getManager();
            if ($form->get('validate')->isClicked()) {  // Si la balf n'a pas besoin d'être modifiée
                $this->serviceCourant->setEmail($currentBalf);
                $this->serviceCourant->validerBalf();
                $em->flush();
                $validBalf = true;
            } elseif ($form->isValid() && $form->get('update')->isClicked()) {    // Si la balf a été modifiée par le service
                $this->serviceCourant->validerBalf();
                $em->flush();
                $balfUpdated = true;
            }
        }
        return $this->render('fiabilisation/sollicitation/miseajour-balf.html.twig', [
            'validBalf'     => $validBalf,
            'balfUpdated'   => $balfUpdated,
            'form'          => $form->createView(),
            'currentBalf'   => $currentBalf
        ]);
    }
}
