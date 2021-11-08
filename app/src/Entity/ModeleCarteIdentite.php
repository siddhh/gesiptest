<?php

namespace App\Entity;

use App\Entity\CarteIdentiteBase;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\ModeleCarteIdentiteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ModeleCarteIdentiteRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class ModeleCarteIdentite extends CarteIdentiteBase
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nomFichier;

    /**
     * @ORM\Column(type="bigint")
     */
    private $tailleFichier;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $actif;

    public function __construct()
    {
        $this->actif = false;
    }


    public function getId(): ?int
    {
        return $this->id;
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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }
}
