<?php

namespace App\Entity\Documentation;

use App\Entity\Traits\HorodatageTrait;
use App\Repository\Documentation\FichierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=FichierRepository::class)
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(
 *  fields={"hash"}
 * )
 */
class Fichier
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $hash;

    /**
     * @ORM\Column(type="smallint")
     */
    private $ordre;

    /**
     * @ORM\Column(type="string", length=128)
     * @Assert\NotBlank
     * @Assert\Length(
     *  max = 64
     * )
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=128)
     */
    private $mimeType;

    /**
     * @ORM\Column(type="string", length=8)
     */
    private $extension;

    /**
     * @ORM\Column(type="bigint")
     */
    private $taille;

    /**
     * @ORM\ManyToOne(targetEntity=Document::class, inversedBy="fichiers")
     */
    private $document;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
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

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getTaille(): ?string
    {
        return $this->taille;
    }

    public function setTaille(string $taille): self
    {
        $this->taille = $taille;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function getSupprimeLe(): ?\DateTimeInterface
    {
        return $this->supprimeLe;
    }

    public function setSupprimeLe(\DateTimeInterface $supprimeLe): self
    {
        $this->supprimeLe = $supprimeLe;

        return $this;
    }
}
