<?php

namespace App\Entity\Meteo;

use App\Entity\Traits\HorodatageTrait;
use App\Repository\Meteo\PublicationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PublicationRepository::class)
 * @ORM\Table(name="meteo_publication")
 * @ORM\HasLifecycleCallbacks()
 */
class Publication
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $periodeDebut;

    /**
     * @ORM\Column(type="datetime")
     */
    private $periodeFin;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriodeDebut(): ?\DateTimeInterface
    {
        return $this->periodeDebut;
    }

    public function setPeriodeDebut(\DateTimeInterface $periodeDebut): self
    {
        $this->periodeDebut = $periodeDebut;

        return $this;
    }

    public function getPeriodeFin(): ?\DateTimeInterface
    {
        return $this->periodeFin;
    }

    public function setPeriodeFin(\DateTimeInterface $periodeFin): self
    {
        $this->periodeFin = $periodeFin;

        return $this;
    }
}
