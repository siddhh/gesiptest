<?php

namespace App\Entity\References;

use App\Repository\References\NatureImpactRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NatureImpactRepository::class)
 */
class NatureImpact extends Reference
{

}
