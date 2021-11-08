<?php

namespace App\Entity\References;

use App\Repository\References\MotifRefusRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MotifRefusRepository::class)
 */
class MotifRefus extends Reference
{

}
