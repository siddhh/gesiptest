<?php

namespace App\Entity;

use App\Entity\CarteIdentite;
use App\Entity\CarteIdentiteEvenement;
use App\Entity\GenericComposantInterface;
use App\Repository\ComposantCarteIdentiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ComposantCarteIdentiteRepository::class)
 * @UniqueEntity("label")
 */
class ComposantCarteIdentite implements GenericComposantInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     * @Assert\Length(
     *  min = 1,
     *  max = 255,
     *  minMessage = "composant.label.min",
     *  maxMessage = "composant.label.max",
     *  allowEmptyString = false
     * )
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentite::class, mappedBy="composantCarteIdentite")
     */
    private $carteIdentites;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentiteEvenement::class, mappedBy="composantCarteIdentite")
     */
    private $carteIdentiteEvenements;

    public function __construct()
    {
        $this->carteIdentites = new ArrayCollection();
        $this->carteIdentiteEvenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|CarteIdentite[]
     */
    public function getCarteIdentites(): Collection
    {
        return $this->carteIdentites;
    }

    public function addCarteIdentite(CarteIdentite $carteIdentite): self
    {
        if (!$this->carteIdentites->contains($carteIdentite)) {
            $this->carteIdentites[] = $carteIdentite;
            $carteIdentite->setComposantCarteIdentite($this);
        }

        return $this;
    }

    public function removeCarteIdentite(CarteIdentite $carteIdentite): self
    {
        if ($this->carteIdentites->contains($carteIdentite)) {
            $this->carteIdentites->removeElement($carteIdentite);
            // set the owning side to null (unless already changed)
            if ($carteIdentite->getComposantCarteIdentite() === $this) {
                $carteIdentite->setComposantCarteIdentite(null);
            }
        }

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
            $carteIdentiteEvenement->setComposantCarteIdentite($this);
        }

        return $this;
    }

    public function removeCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self
    {
        if ($this->carteIdentiteEvenements->contains($carteIdentiteEvenement)) {
            $this->carteIdentiteEvenements->removeElement($carteIdentiteEvenement);
            // set the owning side to null (unless already changed)
            if ($carteIdentiteEvenement->getComposantCarteIdentite() === $this) {
                $carteIdentiteEvenement->setComposantCarteIdentite(null);
            }
        }

        return $this;
    }
}
