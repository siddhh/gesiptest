<?php

namespace App\Entity\References;

use App\Repository\References\MotifRenvoiRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=MotifRenvoiRepository::class)
 */
class MotifRenvoi extends Reference
{
    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=20)
     * @Groups("basique")
     */
    private $type;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
