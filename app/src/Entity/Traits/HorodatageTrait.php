<?php

namespace App\Entity\Traits;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * Trait HorodatageTrait
 * @package App\Entity\Trait
 */
trait HorodatageTrait
{
    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $ajouteLe;

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $majLe;

    /**
     * @return DateTimeInterface|null
     * @throws Exception
     */
    public function getAjouteLe(): ?DateTimeInterface
    {
        return $this->ajouteLe ?? new DateTime();
    }

    /**
     * @param DateTimeInterface $ajouteLe
     * @return $this
     */
    public function setAjouteLe(DateTimeInterface $ajouteLe): self
    {
        $this->ajouteLe = $ajouteLe;

        return $this;
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getMajLe(): ?DateTimeInterface
    {
        return $this->majLe ?? new DateTime();
    }

    /**
     * @param DateTimeInterface $majLe
     * @return $this
     */
    public function setMajLe(DateTimeInterface $majLe): self
    {
        $this->majLe = $majLe;

        return $this;
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function updateTimestamps(): void
    {
        $now = new DateTime();
        $this->setMajLe($now);
        if ($this->getId() === null) {
            $this->setAjouteLe($now);
        }
    }
}
