<?php

namespace App\Workflow;

use App\Entity\Service;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class Action
{
    /** @var MachineEtat */
    private $machine;

    /** @var string NOM */
    public const NOM = "Action";

    /**
     * Constructeur d'une action.
     * @param MachineEtat $machine
     */
    public function __construct(MachineEtat $machine)
    {
        $this->machine = $machine;
    }

    /**
     * Fonction permettant de récupérer le namespace ainsi que le nom de la classe actuelle (pour une utilisation dans twig).
     * @return string
     */
    public function getClassName(): string
    {
        return get_class($this);
    }

    /**
     * Fonction permettant de récupérer uniquement le nom de la classe (pour une utilisation dans twig).
     * @return string
     */
    public function getShortClassName(): string
    {
        $className = explode('\\', get_class($this));
        return array_pop($className);
    }

    /**
     * Fonction permettant de récupérer la machine à état.
     * @return MachineEtat
     */
    protected function getMachineEtat(): MachineEtat
    {
        return $this->machine;
    }

    /**
     * Permet d'accéder aux paramètres d'environnement de Gesip.
     * @param String $clef
     * @return string
     */
    public function getParameter(string $clef): string
    {
        global $kernel;
        return $kernel->getContainer()->getParameter($clef);
    }

    /**
     * Renvoie True, si l'action est exécutable. (Défaut: true)
     * @return bool
     */
    public function peutEtreExecutee(): bool
    {
        // Si l'utilisateur connecté n'a pas les droits suffisants, il ne peut executer aucune action.
        return !$this->getMachineEtat()->serviceEst(Service::ROLE_INVITE);
    }

    /**
     * Renvoie True, si l'utilisateur actuellement connecté est habilité a effectuer cette action. (Défaut: True)
     * @return bool
     */
    public function estHabilite(): bool
    {
        return true;
    }

    /**
     * Génération de la vue (pouvant contenir un formulaire de saisie à afficher lorsque cette demande est à traiter).
     * @return string|null
     */
    abstract public function vue(): ?string;

    /**
     * Traitement de l'action.
     * @param Request|null $request
     * @return JsonResponse
     */
    abstract public function traitement(?Request $request = null): JsonResponse;

    /**
     * Retourne le libellé du bouton à afficher de l'action.
     * @return string|null
     */
    public function getBoutonLibelle(): ?string
    {
        return null;
    }

    /**
     * Retourne les classes du bouton à afficher de l'action.
     * @return string|null
     */
    public function getBoutonClasses(): ?string
    {
        return null;
    }

    /**
     * Retourne une réponse en succès
     * @param array $data
     * @return JsonResponse
     */
    public function retourSuccess(array $data = []): JsonResponse
    {
        return JsonResponse::create(array_merge([ 'status' => 'ok' ], $data));
    }

    /**
     * Retourne une réponse en erreur
     * @param FormInterface|null $form
     * @param array $data
     * @return JsonResponse
     */
    public function retourErreur(?FormInterface $form = null, array $data = []): JsonResponse
    {
        // On récupère les erreurs que l'on met en forme
        $formErrors = [];
        foreach ($form->getErrors(true) as $error) {
            $erreurName = '['.$error->getOrigin()->getName() . ']';
            $p = $error->getOrigin()->getParent();
            while ($p !== null) {
                $tmp = $p->getParent();
                if ($tmp === null) {
                    $erreurName = $p->getName() . $erreurName;
                } else {
                    $erreurName = '[' . $p->getName() .']' . $erreurName;
                }
                $p = $tmp;
            }
            $formErrors[$erreurName] = $error->getMessage();
        }

        // On envoie notre réponse
        return JsonResponse::create(array_merge([ 'status' => 'ko', 'form' => $formErrors ], $data))->setStatusCode(422);
    }

    /**
     * Ajoute un message flash à la session courante.
     *
     * @param string $type
     * @param string $message
     */
    public function addFlash(string $type, string $message)
    {
        $this->getMachineEtat()->getSession()->getFlashBag()->add($type, $message);
    }
}
