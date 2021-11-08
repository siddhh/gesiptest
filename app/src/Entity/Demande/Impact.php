<?php

namespace App\Entity\Demande;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\References\NatureImpact;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Demande\ImpactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ImpactRepository::class)
 * @ORM\Table(name="demande_impact")
 * @ORM\HasLifecycleCallbacks()
 */
class Impact
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     * @Assert\PositiveOrZero
     */
    private $numeroOrdre;

    /**
     * @ORM\ManyToOne(targetEntity=NatureImpact::class)
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull
     */
    private $nature;

    /**
     * @ORM\Column(type="boolean")
     * @Assert\NotNull
     */
    private $certitude;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

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
    private $dateFinMini;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Assert\GreaterThanOrEqual(
     *  propertyPath="dateFinMini"
     * )
     */
    private $dateFinMax;

    /**
     * @ORM\ManyToOne(targetEntity=DemandeIntervention::class, inversedBy="impacts")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    /**
     * @ORM\ManyToMany(targetEntity=Composant::class)
     * @ORM\JoinTable(name="demande_impact_composant")
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

    public function getNumeroOrdre(): ?string
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(string $numeroOrdre): self
    {
        $this->numeroOrdre = $numeroOrdre;

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

    public function getCertitude(): ?bool
    {
        return $this->certitude;
    }

    public function setCertitude(bool $certitude): self
    {
        $this->certitude = $certitude;

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

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFinMini(): ?\DateTimeInterface
    {
        return $this->dateFinMini;
    }

    public function setDateFinMini(?\DateTimeInterface $dateFinMini): self
    {
        $this->dateFinMini = $dateFinMini;

        return $this;
    }

    public function getDateFinMax(): ?\DateTimeInterface
    {
        return $this->dateFinMax;
    }

    public function setDateFinMax(?\DateTimeInterface $dateFinMax): self
    {
        $this->dateFinMax = $dateFinMax;

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

    public function getDureeMinutesMini() : int
    {
        $diff = $this->getDateFinMini()->format('U') - $this->getDateDebut()->format('U');
        return ceil($diff / 60);
    }

    public function getDureeMinutesMaxi() : int
    {
        $diff = $this->getDateFinMax()->format('U') - $this->getDateDebut()->format('U');
        return ceil($diff / 60);
    }
}
