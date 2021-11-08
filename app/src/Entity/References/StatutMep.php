<?php

namespace App\Entity\References;

use App\Entity\MepSsi;
use App\Repository\References\StatutMepRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StatutMepRepository::class)
 */
class StatutMep extends Reference
{
    /**
     * @ORM\OneToMany(targetEntity=MepSsi::class, mappedBy="statut")
     */
    private $mepSsis;

    public function __construct()
    {
        $this->mepSsis = new ArrayCollection();
    }

    /**
     * @return Collection|MepSsi[]
     */
    public function getMepSsis(): Collection
    {
        return $this->mepSsis;
    }

    public function addMepSsi(MepSsi $mepSsi): self
    {
        if (!$this->mepSsis->contains($mepSsi)) {
            $this->mepSsis[] = $mepSsi;
            $mepSsi->setStatut($this);
        }

        return $this;
    }

    public function removeMepSsi(MepSsi $mepSsi): self
    {
        if ($this->mepSsis->contains($mepSsi)) {
            $this->mepSsis->removeElement($mepSsi);
            // set the owning side to null (unless already changed)
            if ($mepSsi->getStatut() === $this) {
                $mepSsi->setStatut(null);
            }
        }

        return $this;
    }
}
