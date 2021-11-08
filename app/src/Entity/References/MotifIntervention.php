<?php

namespace App\Entity\References;

use App\Repository\References\MotifInterventionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MotifInterventionRepository::class)
 */
class MotifIntervention extends Reference
{

}
