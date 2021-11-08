<?php

namespace App\Entity\References;

use App\Repository\References\ListeDiffusionSi2aRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ListeDiffusionSi2aRepository::class)
 */
class ListeDiffusionSi2a extends Reference
{
    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=255)
     * @Groups("basique")
     */
    private $fonction;

    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=255)
     * @Groups("basique")
     */
    private $balp;

    public function getBalp(): ?string
    {
        return $this->balp;
    }

    public function setBalp(string $balp): self
    {
        $this->balp = $balp;

        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(string $fonction): self
    {
        $this->fonction = $fonction;

        return $this;
    }
}
