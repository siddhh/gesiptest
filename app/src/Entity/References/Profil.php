<?php

namespace App\Entity\References;

use App\Repository\References\ProfilRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ProfilRepository::class)
 */
class Profil extends Reference
{

}
