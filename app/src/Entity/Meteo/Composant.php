<?php

namespace App\Entity\Meteo;

use App\Entity\Composant as GesipComposant;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Meteo\ComposantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ComposantRepository::class)
 * @ORM\Table(name="meteo_composant")
 * @ORM\HasLifecycleCallbacks()
 */
class Composant
{
    use HorodatageTrait;

    /** Constantes représentants les différents indices de la météo possibles */
    const ENSOLEILLE = 'ensoleille';
    const NUAGEUX = 'nuageux';
    const ORAGEUX = 'orageux';
    const NC = 'non-communique';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $periodeDebut;

    /**
     * @ORM\Column(type="datetime")
     */
    private $periodeFin;

    /**
     * @ORM\ManyToOne(targetEntity=GesipComposant::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $composant;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $meteo;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $disponibilite;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriodeDebut(): ?\DateTimeInterface
    {
        return $this->periodeDebut;
    }

    public function setPeriodeDebut(\DateTimeInterface $periodeDebut): self
    {
        $this->periodeDebut = $periodeDebut;

        return $this;
    }

    public function getPeriodeFin(): ?\DateTimeInterface
    {
        return $this->periodeFin;
    }

    public function setPeriodeFin(\DateTimeInterface $periodeFin): self
    {
        $this->periodeFin = $periodeFin;

        return $this;
    }

    public function getComposant(): ?GesipComposant
    {
        return $this->composant;
    }

    public function setComposant(?GesipComposant $composant): self
    {
        $this->composant = $composant;

        return $this;
    }

    public function getMeteo(): ?string
    {
        return $this->meteo;
    }

    public function setMeteo(string $meteo): self
    {
        $this->meteo = $meteo;

        return $this;
    }

    public function getDisponibilite(): ?float
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?float $disponibilite): self
    {
        $this->disponibilite = $disponibilite;

        return $this;
    }
}
