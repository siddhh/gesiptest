<?php

namespace App\Entity\Demande;

use App\Entity\Service;
use App\Entity\Traits\HorodatageTrait;
use App\Entity\DemandeIntervention;
use App\Repository\Demande\SaisieRealiseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SaisieRealiseRepository::class)
 * @ORM\Table(name="demande_saisie_realise")
 * @ORM\HasLifecycleCallbacks()
 */
class SaisieRealise
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=DemandeIntervention::class, inversedBy="saisieRealises")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     * @ORM\JoinColumn(nullable=true)
     */
    private $service;

    /**
     * @ORM\OneToMany(targetEntity=ImpactReel::class, mappedBy="saisieRealise")
     * @ORM\OrderBy({"numeroOrdre" = "ASC"})
     */
    private $impactReels;

    /**
     * @ORM\Column(type="text")
     */
    private $resultat;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    public function __construct()
    {
        $this->impactReels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResultat(): ?string
    {
        return $this->resultat;
    }

    public function setResultat(string $resultat): self
    {
        $this->resultat = $resultat;

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

    public function getDemande(): ?DemandeIntervention
    {
        return $this->demande;
    }

    public function setDemande(?DemandeIntervention $demande): self
    {
        $this->demande = $demande;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): self
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @return Collection|ImpactReel[]
     */
    public function getImpactReels(): Collection
    {
        return $this->impactReels;
    }

    public function addImpactReel(ImpactReel $impactReel): self
    {
        if (!$this->impactReels->contains($impactReel)) {
            $this->impactReels[] = $impactReel;
            $impactReel->setSaisieRealise($this);
        }

        return $this;
    }

    public function removeImpactReel(ImpactReel $impactReel): self
    {
        if ($this->impactReels->contains($impactReel)) {
            $this->impactReels->removeElement($impactReel);
            // set the owning side to null (unless already changed)
            if ($impactReel->getSaisieRealise() === $this) {
                $impactReel->setSaisieRealise(null);
            }
        }

        return $this;
    }
}
