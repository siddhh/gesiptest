<?php

namespace App\Entity\Composant;

use App\Entity\Composant;
use App\Entity\Service;
use App\Entity\References\Mission;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Composant\AnnuaireRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=AnnuaireRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Annuaire
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Mission::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $mission;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="annuaire")
     * @ORM\JoinColumn(nullable=false)
     */
    private $service;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class, inversedBy="annuaire")
     * @ORM\JoinColumn(nullable=false)
     */
    private $composant;

    /**
     * @Assert\Email
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $balf;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getSupprimeLe(): ?\DateTimeInterface
    {
        return $this->supprimeLe;
    }

    public function setSupprimeLe(?\DateTimeInterface $supprimeLe): self
    {
        $this->supprimeLe = $supprimeLe;

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

    public function getBalf(): ?string
    {
        if ($this->balf === null) {
            return $this->getService()->getEmail();
        }
        return $this->balf;
    }

    public function setBalf(?string $balf): self
    {
        $this->balf = $balf;
        if ($this->balf === $this->getService()->getEmail()) {
            $this->balf = null;
        }
        return $this;
    }

    public function getInfos(): string
    {
        return $this->getMission()->getLabel() . ' : ' .$this->getService()->getLabel() . ' <' . $this->getBalf() . '>';
    }

    public function __toString()
    {
        return $this->getService()->getLabel();
    }
}
