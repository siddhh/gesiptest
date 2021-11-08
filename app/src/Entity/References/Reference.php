<?php

namespace App\Entity\References;

use App\Entity\Interfaces\HistorisableInterface;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\HorodatageTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(
 *     fields={"label"},
 *     message="reference.label.unique",
 *     repositoryMethod="trouveReferenceParLabel"
 * )
 */
abstract class Reference implements HistorisableInterface
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups("basique")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     * @Assert\Length(
     *     min = 2,
     *     max = 255,
     *     minMessage = "reference.label.min",
     *     maxMessage = "reference.label.max",
     *     allowEmptyString = false
     * )
     * @Groups("basique")
     */
    private $label;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

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

    public function getSupprimeLe(): ?\DateTimeInterface
    {
        return $this->supprimeLe;
    }

    public function setSupprimeLe(?\DateTimeInterface $supprimeLe): self
    {
        $this->supprimeLe = $supprimeLe;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
