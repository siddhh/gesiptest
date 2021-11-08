<?php

namespace App\Entity\Meteo;

use App\Entity\Composant;
use App\Entity\References\ImpactMeteo;
use App\Entity\References\MotifIntervention;
use App\Entity\Demande\ImpactReel;
use App\Entity\Demande\Impact;
use App\Entity\Service;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Meteo\EvenementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EvenementRepository::class)
 * @ORM\Table(name="meteo_evenement")
 * @ORM\HasLifecycleCallbacks()
 */
class Evenement
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $debut;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fin;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class, inversedBy="evenementsMeteo")
     * @ORM\JoinColumn(nullable=false)
     */
    private $composant;

    /**
     * @ORM\ManyToOne(targetEntity=ImpactMeteo::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $impact;

    /**
     * @ORM\ManyToOne(targetEntity=MotifIntervention::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $typeOperation;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     */
    private $saisiePar;

    /**
     * @ORM\ManyToOne(targetEntity=ImpactReel::class)
     */
    private $impactsReel;

    /**
     * @ORM\ManyToOne(targetEntity=Impact::class)
     */
    private $impactsPrevisionnel;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDebut(): ?\DateTimeInterface
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeInterface $debut): self
    {
        $this->debut = $debut;

        return $this;
    }

    public function getFin(): ?\DateTimeInterface
    {
        return $this->fin;
    }

    public function setFin(\DateTimeInterface $fin): self
    {
        $this->fin = $fin;

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

    public function getImpact(): ?ImpactMeteo
    {
        return $this->impact;
    }

    public function setImpact(?ImpactMeteo $impact): self
    {
        $this->impact = $impact;

        return $this;
    }

    public function getTypeOperation(): ?MotifIntervention
    {
        return $this->typeOperation;
    }

    public function setTypeOperation(?MotifIntervention $typeOperation): self
    {
        $this->typeOperation = $typeOperation;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getSaisiePar(): ?Service
    {
        return $this->saisiePar;
    }

    public function setSaisiePar(?Service $saisiePar): self
    {
        $this->saisiePar = $saisiePar;

        return $this;
    }

    public function getImpactReel(): ?ImpactReel
    {
        return $this->impactsReel;
    }

    public function setImpactReel(?ImpactReel $impactReel): self
    {
        $this->impactsReel = $impactReel;

        return $this;
    }

    public function getImpactPrevisionnel(): ?Impact
    {
        return $this->impactsPrevisionnel;
    }

    public function setImpactPrevisionnel(?Impact $impactsPrevisionnel): self
    {
        $this->impactsPrevisionnel = $impactsPrevisionnel;

        return $this;
    }
}
