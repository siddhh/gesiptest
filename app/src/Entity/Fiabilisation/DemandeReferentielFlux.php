<?php

namespace App\Entity\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Fiabilisation\DemandeReferentielFluxRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity(repositoryClass=DemandeReferentielFluxRepository::class)
 */
class DemandeReferentielFlux
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
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="demandesReferentielFlux")
     * @ORM\JoinColumn(nullable=false)
     */
    private $serviceDemandeur;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $type;

    /**
     * ComposantSource IMPACTÉ ComposantTarget
     * @ORM\ManyToOne(targetEntity=Composant::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $composantSource;

    /**
     * ComposantTarget EST IMPACTÉ ComposantSource
     * @ORM\ManyToOne(targetEntity=Composant::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $composantTarget;

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

    public function getComposantSource(): ?Composant
    {
        return $this->composantSource;
    }

    public function setComposantSource(?Composant $composantSource): self
    {
        $this->composantSource = $composantSource;

        return $this;
    }

    public function getComposantTarget(): ?Composant
    {
        return $this->composantTarget;
    }

    public function setComposantTarget(?Composant $composantTarget): self
    {
        $this->composantTarget = $composantTarget;

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
        // Si c'est un ajout, alors on ajoute composantSource dans composantTarget
        if ($this->getType() === self::AJOUT) {
            $this->getComposantTarget()->addComposantsImpacte($this->getComposantSource());
        // Si c'est un retrait, alors on supprime composantSource dans composantTarget
        } elseif ($this->getType() === self::RETRAIT) {
            $this->getComposantTarget()->removeComposantsImpacte($this->getComposantSource());
        }
        return $this;
    }
}
