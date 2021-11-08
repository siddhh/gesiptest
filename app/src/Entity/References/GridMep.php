<?php

namespace App\Entity\References;

use App\Entity\MepSsi;
use App\Repository\References\GridMepRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GridMepRepository::class)
 */
class GridMep extends Reference
{
    /**
     * @ORM\ManyToMany(targetEntity=MepSsi::class, mappedBy="grids")
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
            $mepSsi->addGrid($this);
        }

        return $this;
    }

    public function removeMepSsi(MepSsi $mepSsi): self
    {
        if ($this->mepSsis->contains($mepSsi)) {
            $this->mepSsis->removeElement($mepSsi);
            $mepSsi->removeGrid($this);
        }

        return $this;
    }
}
