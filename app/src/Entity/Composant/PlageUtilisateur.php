<?php

namespace App\Entity\Composant;

use App\Entity\Composant;
use App\Entity\Interfaces\HistorisableInterface;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Composant\PlageUtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use ReflectionClass;

/**
 * @ORM\Entity(repositoryClass=PlageUtilisateurRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class PlageUtilisateur implements HistorisableInterface
{
    use HorodatageTrait;

    /** @var int */
    const LUNDI = 1;
    /** @var int */
    const MARDI = 2;
    /** @var int */
    const MERCREDI = 3;
    /** @var int */
    const JEUDI = 4;
    /** @var int */
    const VENDREDI = 5;
    /** @var int */
    const SAMEDI = 6;
    /** @var int */
    const DIMANCHE = 7;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint")
     */
    private $jour;

    /**
     * @ORM\Column(type="time")
     */
    private $debut;

    /**
     * @ORM\Column(type="time")
     */
    private $fin;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class, inversedBy="plagesUtilisateur")
     * @ORM\JoinColumn(nullable=false)
     */
    private $composant;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJour(): ?int
    {
        return $this->jour;
    }

    public function getJourChaine(): string
    {
        $constants = new ReflectionClass(__CLASS__);
        return ucfirst(mb_strtolower(array_search($this->jour, $constants->getConstants())));
    }

    public function setJour(int $jour): self
    {
        $this->jour = $jour;

        return $this;
    }

    public function getDebut(): ?\DateTimeInterface
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeInterface $debut): self
    {
        $this->debut = $debut;

        return $this;
    }

    public function getFin(): ?\DateTimeInterface
    {
        return $this->fin;
    }

    public function setFin(\DateTimeInterface $fin): self
    {
        $this->fin = $fin;

        return $this;
    }

    public function getTempsTotalEnMinutes(): int
    {
        if ($this->getDebut() > $this->getFin() && $this->getFin()->format('H:i') !== '00:00') {
            return 0;
        } else {
            $fin = $this->getFin();
            if ($this->getFin()->format('H:i') === '00:00') {
                $fin = (clone $fin)->add(new \DateInterval('P1D'));
            }
            return abs($this->getDebut()->getTimestamp() - $fin->getTimestamp()) / 60;
        }
    }

    public function getComposant(): ?Composant
    {
        return $this->composant;
    }

    public function setComposant(?Composant $composant): self
    {
        $this->composant = $composant;

        return $this;
    }

    public function getInfos(): string
    {
        return $this->getJourChaine() . ' : de ' . $this->getDebut()->format('H:i:s') . ' à ' . $this->getFin()->format('H:i:s');
    }
}
