<?php

namespace App\Entity\References;

use App\Entity\Composant;
use App\Repository\References\TypeElementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TypeElementRepository::class)
 */
class TypeElement extends Reference
{
    /**
     * @ORM\OneToMany(targetEntity=Composant::class, mappedBy="typeElement")
     */
    private $composants;

    public function __construct()
    {
        $this->composants = new ArrayCollection();
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
            $composant->setTypeElement($this);
        }

        return $this;
    }

    public function removeComposant(Composant $composant): self
    {
        if ($this->composants->contains($composant)) {
            $this->composants->removeElement($composant);
            // set the owning side to null (unless already changed)
            if ($composant->getTypeElement() === $this) {
                $composant->setTypeElement(null);
            }
        }

        return $this;
    }
}
