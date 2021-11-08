<?php

namespace App\Entity\References;

use App\Repository\References\MissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MissionRepository::class)
 */
class Mission extends Reference
{

}
