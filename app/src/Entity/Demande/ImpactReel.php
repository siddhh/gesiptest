<?php

namespace App\Entity\Demande;

use App\Entity\Composant;
use App\Entity\Demande\SaisieRealise;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Demande\ImpactReelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ImpactReelRepository::class)
 * @ORM\Table(name="demande_impact_reel")
 * @ORM\HasLifecycleCallbacks()
 */
class ImpactReel
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=SaisieRealise::class, inversedBy="impactReels")
     * @ORM\JoinColumn(nullable=false)
     */
    private $saisieRealise;

    /**
     * @ORM\Column(type="integer")
     * @Assert\PositiveOrZero
     */
    private $numeroOrdre;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateDebut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Assert\GreaterThanOrEqual(
     *  propertyPath="dateDebut"
     * )
     */
    private $dateFin;

    /**
     * @ORM\ManyToOne(targetEntity=NatureImpact::class)
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull
     */
    private $nature;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\ManyToMany(targetEntity=Composant::class)
     * @ORM\JoinTable(name="demande_impactreel_composant")
     * @ORM\OrderBy({"label" = "ASC"})
     */
    private $composants;

    public function __construct()
    {
        $this->composants = new ArrayCollection();
    }

    /** Permet d'utiliser array_column pour la propriété id */
    public function __get($prop)
    {
        switch ($prop) {
            default:
                return null;
            case 'id':
                return $this->getId();
        }
    }
    public function __isset($prop) : bool
    {
        switch ($prop) {
            default:
                return false;
            case 'id':
                return isset($this->id);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaisieRealise(): ?SaisieRealise
    {
        return $this->saisieRealise;
    }

    public function setSaisieRealise(?SaisieRealise $saisieRealise): self
    {
        $this->saisieRealise = $saisieRealise;

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

    public function getNumeroOrdre(): ?string
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(string $numeroOrdre): self
    {
        $this->numeroOrdre = $numeroOrdre;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getNature(): ?NatureImpact
    {
        return $this->nature;
    }

    public function setNature(?NatureImpact $nature): self
    {
        $this->nature = $nature;

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

    /**
     * @return Collection|Composant[]
     */
    public function getComposants(): Collection
    {
        return $this->composants;
    }

    public function addComposant(Composant $composant): self
    {
        if (!$this->composants->contains($composant)) {
            $this->composants[] = $composant;
        }

        return $this;
    }

    public function removeComposant(Composant $composant): self
    {
        if ($this->composants->contains($composant)) {
            $this->composants->removeElement($composant);
        }

        return $this;
    }
}
