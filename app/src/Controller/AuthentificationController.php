<?php

namespace App\Controller;

use App\Entity\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AuthentificationController extends AbstractController
{

    /**
     * @Route("/connexion", name="connexion")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // permet de renvoyer l'utilisateur vers une autre page si il est déjà connecté
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // récupère l'erreur de connexion si il y en a eu une
        $erreurAuthentification = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastServiceId = $authenticationUtils->getLastUsername();

        // Récupère la liste des services
        $services = $this->getDoctrine()->getRepository(Service::class)->listeTousServices();

        return $this->render('authentification/connexion.html.twig', [
            'dernier_serviceId'         => $lastServiceId,
            'erreurAuthentification'    => $erreurAuthentification,
            'services'                  => $services
        ]);
    }

    /**
     * @Route("/deconnexion", name="deconnexion")
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Pour accéder à l'écran de changement de mot de passe, taper l'url + /modifierMotdepasse
     * @Route("/modifierMotdepasse/{id}", name="modificationdumotdepasse")
     */
    public function modifierMotdepasse(Service $service, Request $request, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        $routeName = $request->attributes->get('_route');
        $routeParameters = $request->attributes->get('_route_params');

        $erreurMotdepasse = false;
        $form = $this->createFormBuilder(null, [
            'constraints' => [
                new Callback([
                    'callback' => static function (array $data, ExecutionContextInterface $context) {
                        if (!array_key_exists('motdepasse', $data) || !array_key_exists('nouveauMotdepasse', $data)) {
                            return;
                        }
                        if ($data['nouveauMotdepasse'] == $data['motdepasse']) {
                            $context
                                ->buildViolation("Vous devez choisir un mot de passe différent de l'ancien mot de passe.")
                                ->atPath('[nouveauMotdepasse]')
                                ->addViolation()
                            ;
                        }
                    }])
            ]])
        ->add('label', EntityType::class, [
            'class' => Service::class,
            'choice_label' => 'label',
            'multiple' => false,
            'expanded' => false,
            'label' => 'Service :',
            'data' => $service
        ])
        ->add('motdepasse', TextType::class, ['label' => 'Ancien mot de passe* :'])
        ->add('nouveauMotdepasse', RepeatedType::class, [
            'type' => TextType::class,
            'invalid_message' => 'Les nouveaux mots de passe ne correspondent pas.',
            'required' => true,
            'first_options'  => [
                'label' => 'Nouveau mot de passe* :',
                'constraints' => Service::motdepasseValidation()
            ],
            'second_options' => ['label' => 'Vérification du nouveau mot de passe* :'],

        ])
        ->add('boutonvalidation', SubmitType::class, ['label' => 'Valider'])
        ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$passwordEncoder->isPasswordValid($service, $form->get('motdepasse')->getData())) {
                $erreurMotdepasse = true;
            } else {
                $motdepasseCrypte = $passwordEncoder->encodePassword(
                    $service,
                    $form->get('nouveauMotdepasse')->getData()
                );
                $service->setMotdepasse($motdepasseCrypte);
                $service->setResetMotdepasse(false);
                $em = $this->getDoctrine()->getManager();
                $em->persist($service);
                $em->flush();

                $this->addFlash(
                    'success',
                    'Votre mot de passe a été changé avec succès !'
                );
                return $this->redirectToRoute("accueil");
            }
        }

        return $this->render('authentification/changement-de-mot-de-passe.html.twig', [
            'formChangementMotdepasse' => $form->createView(),
            'erreurMotdepasse' => $erreurMotdepasse
        ]);
    }
}
