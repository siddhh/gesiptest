<?php

namespace App\Controller\Gestion;

use App\Entity\CarteIdentite;
use App\Entity\CarteIdentiteEvenement;
use App\Entity\Composant;
use App\Entity\ComposantCarteIdentite;
use App\Entity\ModeleCarteIdentite;
use App\Entity\Service;
use App\Form\CarteIdentiteType;
use App\Form\ModeleCarteIdentiteType;
use App\Service\CarteIdentiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CarteIdentiteController extends AbstractController
{

    /**
     * @Route(
     *      "/gestion/carte-identite/{type?}/{id?}",
     *      name="gestion-carte-identite"),
     *      requirements={
     *          "id"="\d+",
     *          "type"="composant|identite"
     *      }
     * )
     */
    public function index(CarteIdentiteService $carteIdentiteService, ?string $type, ?int $id, Request $request): Response
    {

        // On merge les composants (gesip et composant) sans carte d'identité
        $entityManager = $this->getDoctrine()->getManager();
        $allComposants = array_merge(
            $entityManager->getRepository(Composant::class)->getComposantsAvecCarteIdentite(),
            $entityManager->getRepository(ComposantCarteIdentite::class)->findAll()
        );

        // Récupère les informations nécessaires pour afficher la liste déroulante
        //  et en profite pour récupérer le composant sélectionné et ses cartes d'identité
        $composantSelectionne = null;
        $carteIdentites = [];
        $listeComposantAvecCarteIdentites = [];
        foreach ($allComposants as $composant) {
            $composantId = $composant->getId();
            $typeComposant = Composant::class === get_class($composant) ? 'composant' : 'identite';
            $composantData = [
                'type'      => $typeComposant,
                'id'        => $composantId,
                'label'     => $composant->getLabel(),
                'selected'  => false,
            ];
            if ($id === $composantId && $type === $typeComposant) {
                $composantSelectionne = $composant;
                $composantData['selected'] = true;
            }
            $listeComposantAvecCarteIdentites[$composant->getLabel() . $typeComposant . $composant->getId()] = $composantData;
        }
        ksort($listeComposantAvecCarteIdentites);

        // Récupère les versions de cartes d'identité pour le composant choisi
        $carteIdentiteRepository = $entityManager->getRepository(CarteIdentite::class);
        if (null !== $composantSelectionne) {
            $carteIdentites = $carteIdentiteRepository->getCarteIdentites(['composant' => $composantSelectionne]);
        }

        // Si on doit réaliser une suppression d'une version et connecté en tant que admin
        if ($this->isGranted(Service::ROLE_ADMIN) && null !== ($versionCarteIdentiteId = $request->request->get('carte_identite_supprimer'))) {
            $versionCarteIdentite = $carteIdentiteRepository->find($versionCarteIdentiteId);
            $versionCarteIdentite->setVisible(false);
            $entityManager->flush();
            $this->addFlash('success', 'La version a été supprimée avec succès.');
            // Redirige l'utilisateur vers la gestion de la carte d'identité du composant concerné
            return $this->redirectCarteIdentiteComposant($versionCarteIdentite);
        }

        // Récupération du formulaire d'ajout / modification
        $carteIdentite = new CarteIdentite();
        $formAjoutModification = $this->createForm(CarteIdentiteType::class, $carteIdentite, ['em' => $entityManager]);
        $formAjoutModification->handleRequest($request);
        // Si c'est le formulaire d'ajout / modification qui est recu
        if ($this->isGranted(Service::ROLE_UTILISATEUR) && $formAjoutModification->isSubmitted() && $formAjoutModification->isValid()) {
            // Récupère la version de la carte d'identité, ainsi que les autres paramètres récupérés via le formulaire
            $carteIdentite = $formAjoutModification->getData();
            $action = $formAjoutModification->get('action')->getData();
            $commentaire = $formAjoutModification->get('commentaire')->getData();
            if ('ajout' !== $action) {
                if ($this->isGranted(Service::ROLE_ADMIN)) {
                    $composantForm = $formAjoutModification->get('composant')->getData();
                    $composantLabelForm = $formAjoutModification->get('composantLabel')->getData();
                    // Si le composant a été renommé
                    if ($composantSelectionne && $composantSelectionne->getLabel() !== $composantLabelForm) {
                        // Si on passe d'un Composant à un ComposantCarteIdentite
                        if ($composantSelectionne instanceof Composant && $composantForm === null) {
                            // On crée le ComposantCarteIdentite
                            $newComposantCarteIdentite = new ComposantCarteIdentite();
                            $newComposantCarteIdentite->setLabel($composantLabelForm);
                            $entityManager->persist($newComposantCarteIdentite);
                            // On passe toutes les anciennes CarteIdentite et Evenements vers le nouveau ComposantCarteIdentite
                            foreach ($carteIdentites as $itemCarteIdentites) {
                                $itemCarteIdentites->setComposant(null);
                                $itemCarteIdentites->setComposantCarteIdentite($newComposantCarteIdentite);
                                foreach ($itemCarteIdentites->getCarteIdentiteEvenements() as $itemCarteIdentiteEvenement) {
                                    $itemCarteIdentiteEvenement->setComposant(null);
                                    $itemCarteIdentiteEvenement->setComposantCarteIdentite($newComposantCarteIdentite);
                                }
                            }
                            // On assigne le nouveau ComposantCarteIdentite dans $carteIdentite
                            $carteIdentite->setComposant(null);
                            $carteIdentite->setComposantCarteIdentite($newComposantCarteIdentite);

                        // Si on passe d'un ComposantCarteIdentite à un Composant
                        } elseif ($composantSelectionne instanceof ComposantCarteIdentite && $composantForm instanceof Composant) {
                            // On passe toutes les anciennes CarteIdentite et Evenements vers le nouveau Composant
                            foreach ($carteIdentites as $itemCarteIdentites) {
                                $itemCarteIdentites->setComposant($composantForm);
                                $itemCarteIdentites->setComposantCarteIdentite(null);
                                foreach ($itemCarteIdentites->getCarteIdentiteEvenements() as $itemCarteIdentiteEvenement) {
                                    $itemCarteIdentiteEvenement->setComposant($composantForm);
                                    $itemCarteIdentiteEvenement->setComposantCarteIdentite(null);
                                }
                            }
                            // On assigne le nouveau Composant dans $carteIdentite
                            $carteIdentite->setComposant($composantForm);
                            $carteIdentite->setComposantCarteIdentite(null);
                            // On supprime l'ancien ComposantCarteIdentite
                            $entityManager->remove($composantSelectionne);

                        // Si on passe d'un Composant à un autre Composant
                        } elseif ($composantSelectionne instanceof Composant && $composantForm instanceof Composant) {
                            // On passe toutes les anciennes CarteIdentite et Evenements vers le nouveau Composant
                            foreach ($carteIdentites as $itemCarteIdentites) {
                                $itemCarteIdentites->setComposant($composantForm);
                                foreach ($itemCarteIdentites->getCarteIdentiteEvenements() as $itemCarteIdentiteEvenement) {
                                    $itemCarteIdentiteEvenement->setComposant($composantForm);
                                }
                            }
                            // On assigne le nouveau Composant dans $carteIdentite
                            $carteIdentite->setComposant($composantForm);

                        // Si on renomme un ComposantCarteIdentite déjà existant
                        } elseif ($composantSelectionne instanceof ComposantCarteIdentite && $composantForm === null) {
                            // On renomme le ComposantCarteIdentite déjà existant
                            $composantSelectionne->setLabel($composantLabelForm);
                            // On assigne le ComposantCarteIdentite dans $carteIdentite
                            $carteIdentite->setComposantCarteIdentite($composantSelectionne);
                        }
                    } else {
                        // Intègre le composant
                        if ($composantSelectionne instanceof Composant) {
                            $carteIdentite->setComposant($composantSelectionne);
                        } else {
                            $carteIdentite->setComposantCarteIdentite($composantSelectionne);
                        }
                    }
                } else {
                    // Intègre le composant courant si modification
                    if ($composantSelectionne instanceof Composant) {
                        $carteIdentite->setComposant($composantSelectionne);
                    } else {
                        $carteIdentite->setComposantCarteIdentite($composantSelectionne);
                    }
                }
            } elseif (null === $carteIdentite->getComposant()) {
                // Si pas de composant gesip sélectionné, on associe un nouveau composant 'carte d'identité'
                $composantCarteIdentite = new ComposantCarteIdentite();
                $composantCarteIdentite->setLabel($formAjoutModification->get('composantLabel')->getData());
                $entityManager->persist($composantCarteIdentite);
                $carteIdentite->setComposantCarteIdentite($composantCarteIdentite);
            }
            // Défini le service de la carte d'identité
            $serviceConnecte = $this->getUser();
            $carteIdentite->setService($serviceConnecte);
            // Récupération du fichier et enregistrement de son contenu
            $carteIdentiteFichier = $formAjoutModification->get('fichier')->getData();
            $carteIdentite = $carteIdentiteService->enregistre($carteIdentite, $carteIdentiteFichier);
            // Fait persister cette nouvelle version de carte d'identité en base de données
            $entityManager->persist($carteIdentite);
            $entityManager->flush();
            $flashMessageAction = $action === 'ajout' ? 'l\'ajout' : 'la mise à jour';
            $this->addFlash(
                'success',
                "L'administrateur GESIP a été informé de {$flashMessageAction}."
            );
            // Ajoute un évenement carte d'identité
            $this->ajoutEvenenementCarteIdentite($carteIdentite, $action, $commentaire);
            // Envoi du mail
            $carteIdentiteService->envoyerMail($carteIdentite);
            // Redirige l'utilisateur vers la gestion de la carte d'identité du composant concerné
            return $this->redirectCarteIdentiteComposant($carteIdentite);
        }

        // Séparation de la dernière version et des versions non transmises totalement
        $derniereCarteIdentite = end($carteIdentites);
        $nonCompleteCarteIdentites = [];

        if ($this->isGranted(Service::ROLE_ADMIN)) {
            if ($carteIdentites) {
                foreach ($carteIdentites as $carteIdentite) {
                    if ($carteIdentite->getVisible() && (!$carteIdentite->getTransmissionServiceManager()
                            || !$carteIdentite->getTransmissionSwitch()
                            || !$carteIdentite->getTransmissionSinaps())) {
                        $nonCompleteCarteIdentites[] = $carteIdentite;
                    }
                }
            } else {
                $nonCompleteCarteIdentites = $this->getDoctrine()->getRepository(CarteIdentite::class)->getMajOuCreationParServices();
            }
        }

        // Retourne la vue rendue
        $hasModeleCarteIdentite = null !== $this->getDoctrine()->getManager()->getRepository(ModeleCarteIdentite::class)->getModeleCarteIdentiteActif();
        return $this->render('gestion/carte-identite/index.html.twig', [
            'composantSelectionne'          => $composantSelectionne,
            'listeComposants'               => $listeComposantAvecCarteIdentites,
            'formAjoutModification'         => $formAjoutModification->createView(),
            'derniereCarteIdentite'         => $derniereCarteIdentite,
            'nonCompleteCarteIdentites'     => $nonCompleteCarteIdentites,
            'mimeTypesAutorises'            => CarteIdentiteService::mimeTypesAutorises(),
            'extensionsAutorisees'          => CarteIdentiteService::extensionsAutorisees(),
            'tailleMaximumFichier'          => CarteIdentiteService::getTailleMaximumFichier(),
            'hasActifModeleCarteIdentite'   => $hasModeleCarteIdentite,
        ]);
    }


    /**
     * Redirige vers la page de gestion de la carte d'identité du composant concerné
     */
    private function redirectCarteIdentiteComposant(CarteIdentite $carteIdentite): Response
    {
        $genericComposant = $carteIdentite->getGenericComposant();
        return $this->redirectToRoute('gestion-carte-identite', [
            'type'  => $genericComposant instanceof Composant ? 'composant': 'identite',
            'id'    => $genericComposant->getId(),
        ]);
    }

    /**
     * @Route(
     *      "/carte-identite/{type?}/{id?}",
     *      name="carte-identite-telecharger"),
     *      requirements={
     *          "id"="\d+",
     *          "type"="composant|identite"
     *      }
     * )
     */
    public function telechargerCarteIdentite(?string $type, ?int $id, CarteIdentiteService $carteIdentiteService): BinaryFileResponse
    {
        // Vérifie si une carte d'identité existe bien pour ce composant
        $className = 'composant' === $type ? Composant::class : ComposantCarteIdentite::class;
        $carteIdentite = $this->getDoctrine()->getRepository(CarteIdentite::class)->getCarteIdentiteParComposant($className, $id);
        if (null === $carteIdentite) {
            throw new NotFoundHttpException('Aucune carte d\'identité associée à ce composant');
        }

        // Forme le nom du fichier
        $composantLabel = $carteIdentite->getGenericComposant()->getLabel();
        $carteIdentiteDateString = $carteIdentite->getAjouteLe()->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('Ymd-Hi');
        $nomFichierAffiche = "carte_identite_{$composantLabel}_{$carteIdentiteDateString}";

        // Envoi du fichier
        return $carteIdentiteService->getFichierReponse($carteIdentite, $nomFichierAffiche);
    }

    /**
     * Historisation de l ajout ou modification des cartes d'identités
     * @param CarteIdentite $carteIdentite
     * @param string $evenement
     * @return void
     */
    public function ajoutEvenenementCarteIdentite(CarteIdentite $carteIdentite, string $evenement, string $commentaire = null): void
    {
        $evenementLabel = $evenement === 'ajout' ? 'Nouvel enregistrement' : 'Transmision aux administrateurs';
        $em = $this->getDoctrine()->getManager();
        $carteIdentiteEvenenement = new CarteIdentiteEvenement();
        $carteIdentiteEvenenement->setGenericComposant($carteIdentite->getGenericComposant());
        $carteIdentiteEvenenement->setService($carteIdentite->getService());
        $carteIdentiteEvenenement->setEvenement($evenementLabel);
        $carteIdentiteEvenenement->setCommentaire($commentaire);
        $carteIdentiteEvenenement->setCarteIdentite($carteIdentite);
        $em->persist($carteIdentiteEvenenement);
        $em->flush();
    }

    /**
     * Affiche la page de gestion du modèle de carte d'identité
     * @Route("/gestion/modele-carte-identite", name="gerer-modele-carte-identite")
     * @return Response
     */
    public function gererModeleCarteIdentite(CarteIdentiteService $carteIdentiteService, Request $request): Response
    {
        // Récupération du formulaire de gestion des modèles de carte d'identité
        $modeleCarteIdentite = new ModeleCarteIdentite();
        $modeleCarteIdentiteRepository = $this->getDoctrine()->getRepository(ModeleCarteIdentite::class);
        $form = $this->createForm(ModeleCarteIdentiteType::class, $modeleCarteIdentite);
        $form->handleRequest($request);

        // Si le formulaire est validé
        if ($form->isSubmitted() && $form->isValid()) {
            // Enregistre le nouveau modèle
            $modeleCarteIdentite = $form->getData();
            $modeleCarteIdentiteFichier = $form->get('fichier')->getData();
            $modeleCarteIdentite = $carteIdentiteService->enregistre($modeleCarteIdentite, $modeleCarteIdentiteFichier);
            $em = $this->getDoctrine()->getManager();
            $em->persist($modeleCarteIdentite);
            $em->flush();

            // Si le nouveau modèle est marqué actif, il faut  désactiver les autres !
            if ($modeleCarteIdentite->getActif()) {
                $modeleCarteIdentiteRepository->activeModele($modeleCarteIdentite);
            }

            // Affiche un message de confirmation à l'utilisateur
            $this->addFlash(
                'success',
                "Modèle de carte d'identité ajouté avec succès."
            );

            // Remet le formulaire à blanc après un ajout réussi
            $form = $this->createForm(ModeleCarteIdentiteType::class, new ModeleCarteIdentite());
        }

        // Récupère la liste des modèles de carte d'identité
        $listeModeleCarteIdentite = $this->getDoctrine()->getRepository(ModeleCarteIdentite::class)
            ->findBy([], ['ajouteLe' => 'DESC']);

        // Retourne la vue rendue
        return $this->render('gestion/carte-identite/modele-carte-identite.html.twig', [
            'mimeTypesAutorises'        => CarteIdentiteService::mimeTypesAutorises(),
            'extensionsAutorisees'      => CarteIdentiteService::extensionsAutorisees(),
            'tailleMaximumFichier'      => CarteIdentiteService::getTailleMaximumFichier(),
            'formModeleCarteIdentite'   => $form->createView(),
            'listeModeleCarteIdentite'  => $listeModeleCarteIdentite,
        ]);
    }

    /**
     * @Route(
     *      "/modele-carte-identite/{id?}",
     *      name="modele-carte-identite-telecharger"),
     *      requirements={
     *          "id"="\d+",
     *      }
     * )
     */
    public function telechargerModeleCarteIdentite(?int $id, CarteIdentiteService $carteIdentiteService): BinaryFileResponse
    {
        // Recupère le modèle de carte d'identité voulu
        $modeleCarteIdentiteRepository = $this->getDoctrine()->getRepository(ModeleCarteIdentite::class);
        if (empty($id)) {
            $modeleCarteIdentite = $modeleCarteIdentiteRepository->getModeleCarteIdentiteActif();
        } else {
            $modeleCarteIdentite = $modeleCarteIdentiteRepository->find($id);
        }
        if (null === $modeleCarteIdentite) {
            throw new NotFoundHttpException('Modèle de carte d\'identité introuvable.');
        } elseif (!$this->isGranted(Service::ROLE_ADMIN) && !$modeleCarteIdentite->getActif()) {
            throw new AccessDeniedHttpException('Ce modèle de carte d\'identité n\'est pas ou n\'est plus actif. Impossible de le télécharger.');
        }

        // Forme le nom du fichier
        $dateString = $modeleCarteIdentite->getAjouteLe()->setTimeZone(new \DateTimeZone('Europe/Paris'))->format('Ymd_His');
        $nomFichierAffiche = "modele_carte_identite-{$dateString}";

        // Envoi du fichier
        return $carteIdentiteService->getFichierReponse($modeleCarteIdentite, $nomFichierAffiche);
    }
}
