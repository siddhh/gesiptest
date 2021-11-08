<?php

namespace App\Entity;

use App\Entity\CarteIdentite;
use App\Entity\Composant;
use App\Entity\ComposantCarteIdentite;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\CarteIdentiteEvenementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CarteIdentiteEvenementRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class CarteIdentiteEvenement
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class, inversedBy="carteIdentiteEvenements")
     * @ORM\JoinColumn(nullable=true)
     */
    private $composant;

    /**
     * @ORM\ManyToOne(targetEntity=ComposantCarteIdentite::class, inversedBy="carteIdentiteEvenements")
     * @ORM\JoinColumn(nullable=true)
     */
    private $composantCarteIdentite;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="carteIdentiteEvenements")
     * @ORM\JoinColumn(nullable=false)
     */
    private $service;

    /**
     * @ORM\Column(type="string", length=500)
     */
    private $evenement;

    /**
     * @ORM\ManyToOne(targetEntity=CarteIdentite::class, inversedBy="carteIdentiteEvenements")
     * @ORM\JoinColumn(nullable=true)
     */
    private $carteIdentite;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getComposantCarteIdentite(): ?ComposantCarteIdentite
    {
        return $this->composantCarteIdentite;
    }

    public function setComposantCarteIdentite(?ComposantCarteIdentite $composantCarteIdentite): self
    {
        $this->composantCarteIdentite = $composantCarteIdentite;

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

    public function getEvenement(): ?string
    {
        return $this->evenement;
    }

    public function setEvenement(string $evenement): self
    {
        $this->evenement = $evenement;

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

    public function getCarteIdentite(): ?CarteIdentite
    {
        return $this->carteIdentite;
    }

    public function setCarteIdentite(?CarteIdentite $carteIdentite): self
    {
        $this->carteIdentite = $carteIdentite;

        return $this;
    }

    /*
     * Propose des méthodes permettant de "bridger" la propriété composant
     */
    public function getGenericComposant() : ?GenericComposantInterface
    {
        return null === $this->composant ? $this->getComposantCarteIdentite() : $this->getComposant();
    }

    public function setGenericComposant(?GenericComposantInterface $genericComposant): self
    {
        if ($genericComposant instanceof Composant) {
            $this->composant = $genericComposant;
        } else {
            $this->composantCarteIdentite = $genericComposant;
        }
        return $this;
    }
}
