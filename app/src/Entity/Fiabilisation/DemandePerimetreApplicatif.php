<?php

namespace App\Entity\Fiabilisation;

use App\Entity\ActionHistory;
use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\References\Mission;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Fiabilisation\DemandePerimetreApplicatifRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass=DemandePerimetreApplicatifRepository::class)
 */
class DemandePerimetreApplicatif
{
    use HorodatageTrait;

    /** @var string */
    const AJOUT = "add";
    /** @var string */
    const RETRAIT = "remove";

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="demandesPerimetreApplicatif")
     * @ORM\JoinColumn(nullable=false)
     */
    private $serviceDemandeur;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $composant;

    /**
     * @ORM\ManyToOne(targetEntity=Mission::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $mission;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $accepteLe;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     */
    private $acceptePar;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $refuseLe;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     */
    private $refusePar;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $annuleLe;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     */
    private $annulePar;

    /** @var Composant\Annuaire[] $annuairesHistorique */
    private $annuairesHistorique;

    public function __construct()
    {
        $this->annuairesHistorique = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceDemandeur(): ?Service
    {
        return $this->serviceDemandeur;
    }

    public function setServiceDemandeur(?Service $serviceDemandeur): self
    {
        $this->serviceDemandeur = $serviceDemandeur;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getComposant(): ?Composant
    {
        return $this->composant;
    }

    public function setComposant(?Composant $composant): self
    {
        $this->composant = $composant;

        return $this;
    }

    public function getMission(): ?Mission
    {
        return $this->mission;
    }

    public function setMission(?Mission $mission): self
    {
        $this->mission = $mission;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getAccepteLe(): ?\DateTimeInterface
    {
        return $this->accepteLe;
    }

    public function setAccepteLe(?\DateTimeInterface $accepteLe): self
    {
        $this->accepteLe = $accepteLe;

        return $this;
    }

    public function getAcceptePar(): ?Service
    {
        return $this->acceptePar;
    }

    public function setAcceptePar(?Service $acceptePar): self
    {
        $this->acceptePar = $acceptePar;

        return $this;
    }

    public function getRefuseLe(): ?\DateTimeInterface
    {
        return $this->refuseLe;
    }

    public function setRefuseLe(?\DateTimeInterface $refuseLe): self
    {
        $this->refuseLe = $refuseLe;

        return $this;
    }

    public function getRefusePar(): ?Service
    {
        return $this->refusePar;
    }

    public function setRefusePar(?Service $refusePar): self
    {
        $this->refusePar = $refusePar;

        return $this;
    }

    public function getAnnuleLe(): ?\DateTimeInterface
    {
        return $this->annuleLe;
    }

    public function setAnnuleLe(?\DateTimeInterface $annuleLe): self
    {
        $this->annuleLe = $annuleLe;

        return $this;
    }

    public function getAnnulePar(): ?Service
    {
        return $this->annulePar;
    }

    public function setAnnulePar(?Service $annulePar): self
    {
        $this->annulePar = $annulePar;

        return $this;
    }

    /**
     * Permet d'accepter la demande
     * Attention ! On n'applique pas la demande :-)
     * @param Service $actionPar
     * @return $this
     */
    public function accepter(Service $actionPar): self
    {
        $this->setAccepteLe(new \DateTime());
        $this->setAcceptePar($actionPar);
        return $this;
    }

    /**
     * Permet de refuser la demande
     * @param Service $actionPar
     * @return $this
     */
    public function refuser(Service $actionPar): self
    {
        $this->setRefuseLe(new \DateTime());
        $this->setRefusePar($actionPar);
        return $this;
    }

    /**
     * Permet d'annuler la demande
     * @param Service $actionPar
     * @return $this
     */
    public function annuler(Service $actionPar): self
    {
        $this->setAnnuleLe(new \DateTime());
        $this->setAnnulePar($actionPar);
        return $this;
    }

    /**
     * Fonction permettant de savoir si la demande est en attente
     * (true si elle est en attente, false sinon.)
     * @return bool
     */
    public function estEnAttente(): bool
    {
        return (
            $this->getAccepteLe() === null &&
            $this->getRefuseLe() === null &&
            $this->getAnnuleLe() === null
        );
    }

    /**
     * Fonction permettant d'appliquer la demande à la base de données
     * @return $this
     */
    public function appliquer(): self
    {
        // Si c'est un ajout, alors on ajoute une entrée dans l'annuaire du composant
        if ($this->getType() === self::AJOUT) {
            // On initialise une entrée d'annuaire
            $annuaire = new Composant\Annuaire();
            $annuaire->setService($this->getServiceDemandeur());
            $annuaire->setMission($this->getMission());
            // On ajoute l'entrée dans l'annuaire du composant
            $this->getComposant()->addAnnuaire($annuaire);
            $this->annuairesHistorique[] = $annuaire;
        // Si c'est un retrait, alors on supprime l'annuaire
        } elseif ($this->getType() === self::RETRAIT) {
            // On récupère l(es) entrée(s) d'annuaire dans le composant ciblé et correspondant(s) à la mission et au service demandeur
            $annuaires = $this->getComposant()->getAnnuaire()->filter(function (Composant\Annuaire $annuaire) {
                return (
                   $annuaire->getService() === $this->getServiceDemandeur() &&
                   $annuaire->getMission() === $this->getMission()
                );
            });
            // On supprime cette entrée dans l'annuaire
            foreach ($annuaires as $an) {
                $this->annuairesHistorique[] = $an;
                $this->getComposant()->removeAnnuaire($an);
            }
        }
        return $this;
    }

    /**
     * Fonction permettant d'enregistrer l'action history en base de données suite à l'application de la demande.
     *
     * @param ObjectManager $em
     * @param Request       $request
     * @param Service       $serviceCourant
     *
     * @return $this
     */
    public function enregistrementActionHistory(ObjectManager $em, Request $request, Service $serviceCourant): self
    {
        // Si une demande a été appliquée avant
        if ($this->annuairesHistorique) {
            // On initialise les détails que l'on souhaite modifier
            $details = ['old' => [ 'annuaire' => [] ], 'new' => [ 'annuaire' => [] ]];
            if ($this->type === self::AJOUT) {
                foreach ($this->annuairesHistorique as $annuaire) {
                    $details['new']['annuaire'][] = $annuaire->getInfos();
                }
            } elseif ($this->type === self::RETRAIT) {
                foreach ($this->annuairesHistorique as $annuaire) {
                    $details['old']['annuaire'][] = $annuaire->getInfos();
                }
            }

            // Si il y a eu des modifications, alors on crée l'ActionHistory en base
            if ($details['old'] !== $details['new']) {
                $actionHistory = new ActionHistory();
                $actionHistory->setAction(ActionHistory::UPDATE);
                $actionHistory->setActionDate(new \DateTime());
                $actionHistory->setIp($request->getClientIp());
                $actionHistory->setServiceId($serviceCourant->getId());
                $actionHistory->setObjetClasse(Composant::class);
                $actionHistory->setObjetId($this->getComposant()->getId());
                $actionHistory->setDetails($details);
                $em->persist($actionHistory);
            }
        }
        return $this;
    }
}
