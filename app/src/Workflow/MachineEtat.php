<?php

namespace App\Workflow;

use App\Entity\Demande\HistoriqueStatus;
use App\Entity\DemandeIntervention;
use App\Entity\Service;
use App\Workflow\Exceptions\ActionInterditeException;
use App\Workflow\Exceptions\EntreeImpossibleException;
use App\Workflow\Exceptions\SortieImpossibleException;
use Doctrine\Migrations\Exception\MissingDependency;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class MachineEtat
{
    /** @var DemandeIntervention */
    private $demandeIntervention;
    /** @var Service|null */
    private $serviceConnecte;
    /** @var Etat */
    private $etatActuel;

    /** @var EntityManager */
    private $entityManager;
    /** @var MailerInterface */
    private $mailer;

    /**
     * Constructeur de la machine à état associée à une demande d'intervention
     * @param DemandeIntervention $demandeIntervention
     * @param Service|null $serviceConnecte
     */
    public function __construct(DemandeIntervention $demandeIntervention, ?Service $serviceConnecte = null)
    {
        // On récupère la demande, le service connecté ainsi que l'entity manager
        $this->demandeIntervention = $demandeIntervention;
        $this->serviceConnecte = $serviceConnecte;

        // Si nous n'avons pas passé de service en paramètre, alors on récupère celui celui connecté
        if ($this->serviceConnecte === null) {
            global $kernel;
            $token = $kernel->getContainer()->get('security.token_storage')->getToken();
            if (!is_null($token) && $token->getUser() instanceof Service) {
                $this->serviceConnecte = $token->getUser();
            }
        }

        // On instancie un objet état, représentant l'état actuel de la demande d'intervention
        $className = $this->demandeIntervention->getStatus();

        if (get_class_methods($className) !== null) {
            $this->etatActuel = new $className($this);
            $this->etatActuel->hydraterDonnees($this->demandeIntervention->getStatusDonnees());
        }
    }

    /**
     * Fonction permettant de dire si le service courant a le rôle passé en paramètre.
     * @param string $role
     * @return bool
     */
    public function serviceEst(string $role): bool
    {
        global $kernel;
        return $kernel->getContainer()->get('security.authorization_checker')->isGranted($role);
    }

    /**
     * Fonction permettant d'enregistrer l'entity manager si besoin.
     * @param EntityManager $em
     * @return $this
     */
    public function setEntityManager(EntityManager $em): self
    {
        $this->entityManager = $em;
        return $this;
    }

    /**
     * Fonction permettant de récupérer l'entity manager.
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * Fonction permettant d'enregistrer le mailer si besoin.
     * @param MailerInterface $mailer
     * @return $this
     */
    public function setMailer(MailerInterface $mailer): self
    {
        $this->mailer = $mailer;
        return $this;
    }

    /**
     * Fonction permettant de récupérer le mailer.
     * @return MailerInterface
     */
    public function getMailer(): MailerInterface
    {
        return $this->mailer;
    }

    /**
     * Fonction permettant de récupérer le service twig
     * @return Environment
     */
    public function getTwig(): Environment
    {
        global $kernel;
        return $kernel->getContainer()->get('twig');
    }

    /**
     * Fonction permettant de récupérer le service session
     * @return Session
     */
    public function getSession(): Session
    {
        global $kernel;
        return $kernel->getContainer()->get('session');
    }

    /**
     * Fonction permettant de récupérer le form builder
     * @param string $type
     * @param array $data
     * @param array $options
     * @return FormInterface
     */
    public function getFormBuilder(string $type, $data = [], array $options = []): FormInterface
    {
        global $kernel;
        return $kernel->getContainer()->get('form.factory')->create($type, $data, array_merge([ 'csrf_protection' => false ], $options));
    }

    /**
     * On récupère la demande associée à la machine à état.
     * @return DemandeIntervention
     */
    public function getDemandeIntervention(): DemandeIntervention
    {
        return $this->demandeIntervention;
    }

    /**
     * Fonction permettant de faire changer l'état de la demande d'intervention.
     * @param string $etatClass
     * @param array|null $donnees
     * @throws MissingDependency
     * @throws EntreeImpossibleException
     * @throws SortieImpossibleException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function changerEtat(string $etatClass, array $donnees = []): void
    {
        // Si nous ne pouvons pas sortir de l'état actuel
        if (!$this->etatActuel->peutSortir()) {
            throw new SortieImpossibleException($this->etatActuel);
        }

        // On instancie notre état suivant
        /** @var Etat $nouvelEtat */
        $nouvelEtat = new $etatClass($this, $donnees);

        // On vérifie que l'on peut entrer dans le nouvel état
        if (!$nouvelEtat->peutEntrer()) {
            throw new EntreeImpossibleException($nouvelEtat);
        }

        // On met à jour le statut dans la machine à état également
        $this->etatActuel = $nouvelEtat;

        // On met à jour la demande d'intervention
        $this->demandeIntervention->setStatus($etatClass);
        $this->demandeIntervention->setStatusDonnees($nouvelEtat->getDonnees());

        // Si tout est ok, alors met en place le nouveau statut
        $historique = new HistoriqueStatus();
        $historique->setStatus($etatClass);
        $historique->setDonnees($donnees);
        $this->demandeIntervention->addHistoriqueStatus($historique);
    }

    /**
     * On récupère le service atuellement connecté.
     * @return Service|null
     */
    public function getServiceConnecte(): ?Service
    {
        return $this->serviceConnecte;
    }

    /**
     * On récupère l'état actuel de la demande d'intervention.
     * @return Etat|null
     */
    public function getEtatActuel(): ?Etat
    {
        return $this->etatActuel;
    }

    /**
     * On récupère les actions suivantes.
     * @return array
     */
    public function getActionsSuivantes(): array
    {
        return $this->etatActuel->getActions();
    }

    /**
     * Permet d'exécuter une action définie.
     * @param string $actionClass
     * @param Request|null $request
     * @throws ActionInterditeException
     * @return JsonResponse
     */
    public function executerAction(string $actionClass, ?Request $request = null): JsonResponse
    {
        // On instancie notre action
        /** @var Action $action */
        $action = new $actionClass($this);

        // On vérifie que l'action est bien possible actuellement
        // (dans la liste des actions possibles de l'état en cours, ainsi que si l'utilisateur a les droits de l'exécuter)
        if (!in_array($actionClass, $this->getEtatActuel()->getActions()) || !$action->peutEtreExecutee()) {
            throw new ActionInterditeException($action, $this);
        }

        // On traite l'action
        return $action->traitement($request);
    }

    /**
     * Fonction listant tous les libellés d'état
     */
    public static function getEtatLibelles(): array
    {
        return [
            Etats\EtatAccordee::class                  => 'Accordée',
            Etats\EtatAnalyseEnCours::class            => 'En cours d\'analyse',
            Etats\EtatAnnulee::class                   => 'Annulée',
            Etats\EtatBrouillon::class                 => 'Brouillon',
            Etats\EtatConsultationEnCours::class       => 'Consultation en cours',
            Etats\EtatConsultationEnCoursCdb::class    => 'Consultation en cours par le Cdb',
            Etats\EtatInstruite::class                 => 'Instruite',
            Etats\EtatInterventionEnCours::class       => 'Intervention en cours',
            Etats\EtatRefusee::class                   => 'Refusée',
            Etats\EtatRenvoyee::class                  => 'Renvoyée',
            Etats\EtatSaisirRealise::class             => 'Saisie réalisée',
            Etats\EtatTerminee::class                  => 'Terminée',
        ];
    }
}
