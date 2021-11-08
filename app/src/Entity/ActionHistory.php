<?php

namespace App\Entity;

use App\Repository\ActionHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ActionHistoryRepository::class)
 * @ORM\Table(
 *      indexes={
 *          @ORM\Index(name="idx_logactions_date", columns={"action_date"}),
 *          @ORM\Index(name="idx_logactions_ip", columns={"ip"})
 *      }
 * )
 * @ORM\HasLifecycleCallbacks()
 */
class ActionHistory
{
    /** @var string */
    const CREATE = "create";
    /** @var string */
    const UPDATE = "update";
    /** @var string */
    const REMOVE = "remove";

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $actionDate;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $ip;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $serviceId;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $objetClasse;

    /**
     * @ORM\Column(type="integer")
     */
    private $objetId;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $action;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $details = [];


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActionDate(): ?\DateTimeInterface
    {
        return $this->actionDate;
    }

    public function setActionDate(\DateTimeInterface $actionDate): self
    {
        $this->actionDate = $actionDate;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getServiceId(): ?int
    {
        return $this->serviceId;
    }

    public function setServiceId(?int $serviceId): self
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    public function getObjetClasse(): ?string
    {
        return $this->objetClasse;
    }

    public function setObjetClasse(string $objetClasse): self
    {
        $this->objetClasse = $objetClasse;

        return $this;
    }

    public function getObjetId(): ?int
    {
        return $this->objetId;
    }

    public function setObjetId(int $objetId): self
    {
        $this->objetId = $objetId;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): self
    {
        $this->details = $details;

        return $this;
    }

    /**
     * @ORM\PrePersist()
     */
    public function updateActionDate(): void
    {
        if ($this->getId() === null && $this->getActionDate() === null) {
            $this->setActionDate(new \DateTime());
        }
    }
}
