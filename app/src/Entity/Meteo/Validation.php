<?php

namespace App\Entity\Meteo;

use App\Entity\Service;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Meteo\ValidationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ValidationRepository::class)
 * @ORM\Table(name="meteo_validation")
 * @ORM\HasLifecycleCallbacks()
 */
class Validation
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
    private $periodeDebut;

    /**
     * @ORM\Column(type="datetime")
     */
    private $periodeFin;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $exploitant;

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

    public function getExploitant(): ?Service
    {
        return $this->exploitant;
    }

    public function setExploitant(?Service $exploitant): self
    {
        $this->exploitant = $exploitant;

        return $this;
    }
}
