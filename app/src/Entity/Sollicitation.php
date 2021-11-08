<?php

namespace App\Entity;

use App\Entity\Traits\HorodatageTrait;
use App\Repository\SollicitationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SollicitationRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Sollicitation
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $serviceSollicite;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $sollicitePar;

    /**
     * @ORM\Column(type="datetime")
     */
    private $solliciteLe;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceSollicite(): ?Service
    {
        return $this->serviceSollicite;
    }

    public function setServiceSollicite(?Service $serviceSollicite): self
    {
        $this->serviceSollicite = $serviceSollicite;

        return $this;
    }

    public function getSollicitePar(): ?Service
    {
        return $this->sollicitePar;
    }

    public function setSollicitePar(?Service $sollicitePar): self
    {
        $this->sollicitePar = $sollicitePar;

        return $this;
    }

    public function getSolliciteLe(): ?\DateTimeInterface
    {
        return $this->solliciteLe;
    }

    public function setSolliciteLe(\DateTimeInterface $solliciteLe): self
    {
        $this->solliciteLe = $solliciteLe;

        return $this;
    }
}
