<?php

namespace App\Entity;

use Doctrine\Common\Collections\Collection;

interface GenericComposantInterface
{
    public function getId(): ?int;
    public function getLabel(): ?string;
    public function setLabel(string $label): self;
    public function getCarteIdentites(): Collection;
    public function addCarteIdentite(CarteIdentite $carteIdentite): self;
    public function removeCarteIdentite(CarteIdentite $carteIdentite): self;
    public function getCarteIdentiteEvenements(): Collection;
    public function addCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self;
    public function removeCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self;
}
