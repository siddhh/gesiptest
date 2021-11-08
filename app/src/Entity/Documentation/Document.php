<?php

namespace App\Entity\Documentation;

use App\Repository\Documentation\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\HorodatageTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=DocumentRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Document
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
     * @Assert\NotBlank
     * @Assert\Length(
     *  max = 64
     * )
     */
    private $titre;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank
     */
    private $description;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=16)
     * @Assert\NotBlank
     * @Assert\Length(
     *  max = 16
     * )
     */
    private $version;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank
     */
    private $destinataires;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

    /**
     * @ORM\OneToMany(targetEntity=Fichier::class, mappedBy="document")
     * @ORM\OrderBy({"ordre" = "ASC"})
     */
    private $fichiers;

    public function __construct()
    {
        $this->fichiers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getDestinataires(): ?string
    {
        return $this->destinataires;
    }

    public function setDestinataires(string $destinataires): self
    {
        $this->destinataires = $destinataires;

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

    /**
     * @return Collection|Fichier[]
     */
    public function getFichiers(): Collection
    {
        return $this->fichiers;
    }

    public function addFichier(Fichier $fichier): self
    {
        if (!$this->fichiers->contains($fichier)) {
            $this->fichiers[] = $fichier;
            $fichier->setDocument($this);
        }

        return $this;
    }

    public function removeFichier(Fichier $fichier): self
    {
        if ($this->fichiers->contains($fichier)) {
            $this->fichiers->removeElement($fichier);
            // set the owning side to null (unless already changed)
            if ($fichier->getDocument() === $this) {
                $fichier->setDocument(null);
            }
        }

        return $this;
    }
}
