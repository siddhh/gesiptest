<?php

namespace App\Entity\References;

use App\Repository\References\ImpactMeteoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ImpactMeteoRepository::class)
 */
class ImpactMeteo extends Reference
{

}
