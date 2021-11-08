<?php

namespace App\Entity;

use App\Entity\CarteIdentiteBase;
use App\Entity\CarteIdentiteEvenement;
use App\Entity\Composant;
use App\Entity\ComposantCarteIdentite;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\CarteIdentiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CarteIdentiteRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class CarteIdentite extends CarteIdentiteBase
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class, inversedBy="carteIdentites")
     * @ORM\JoinColumn(nullable=true)
     */
    private $composant;

    /**
     * @ORM\ManyToOne(targetEntity=ComposantCarteIdentite::class, inversedBy="carteIdentites")
     * @ORM\JoinColumn(nullable=true)
     */
    private $composantCarteIdentite;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="carteIdentites")
     * @ORM\JoinColumn(nullable=false)
     */
    private $service;

    /**
     * @ORM\Column(type="string", length=500)
     */
    private $nomFichier;

    /**
     * @ORM\Column(type="bigint")
     */
    private $tailleFichier;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $transmissionServiceManager;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $transmissionSwitch;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $transmissionSinaps;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $visible;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentiteEvenement::class, mappedBy="carteIdentite")
     * @ORM\OrderBy({"id" = "DESC"})
     */
    private $carteIdentiteEvenements;

    public function __construct()
    {
        $this->transmissionServiceManager = false;
        $this->transmissionSwitch = false;
        $this->transmissionSinaps = false;
        $this->visible = true;
        $this->carteIdentiteEvenements = new ArrayCollection();
    }

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

    public function getNomFichier(): ?string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(string $nomFichier): self
    {
        $this->nomFichier = $nomFichier;

        return $this;
    }

    public function getTailleFichier(): ?string
    {
        return $this->tailleFichier;
    }

    public function setTailleFichier(string $tailleFichier): self
    {
        $this->tailleFichier = $tailleFichier;

        return $this;
    }

    public function getTransmissionServiceManager(): ?bool
    {
        return $this->transmissionServiceManager;
    }

    public function setTransmissionServiceManager(bool $transmissionServiceManager): self
    {
        $this->transmissionServiceManager = $transmissionServiceManager;

        return $this;
    }

    public function getTransmissionSwitch(): ?bool
    {
        return $this->transmissionSwitch;
    }

    public function setTransmissionSwitch(bool $transmissionSwitch): self
    {
        $this->transmissionSwitch = $transmissionSwitch;

        return $this;
    }

    public function getTransmissionSinaps(): ?bool
    {
        return $this->transmissionSinaps;
    }

    public function setTransmissionSinaps(bool $transmissionSinaps): self
    {
        $this->transmissionSinaps = $transmissionSinaps;

        return $this;
    }

    public function getVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @return Collection|CarteIdentiteEvenement[]
     */
    public function getCarteIdentiteEvenements(): Collection
    {
        return $this->carteIdentiteEvenements;
    }

    public function addCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self
    {
        if (!$this->carteIdentiteEvenements->contains($carteIdentiteEvenement)) {
            $this->carteIdentiteEvenements[] = $carteIdentiteEvenement;
            $carteIdentiteEvenement->setCarteIdentite($this);
        }

        return $this;
    }

    public function removeCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self
    {
        if ($this->carteIdentiteEvenements->contains($carteIdentiteEvenement)) {
            $this->carteIdentiteEvenements->removeElement($carteIdentiteEvenement);
            // set the owning side to null (unless already changed)
            if ($carteIdentiteEvenement->getCarteIdentite() === $this) {
                $carteIdentiteEvenement->setCarteIdentite(null);
            }
        }

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
