<?php

namespace App\Entity\Demande;

use App\Entity\DemandeIntervention;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\Demande\HistoriqueStatusRepository;
use App\Workflow\Etat;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=HistoriqueStatusRepository::class)
 * @ORM\Table(name="demande_historique_status")
 * @ORM\HasLifecycleCallbacks()
 */
class HistoriqueStatus
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=DemandeIntervention::class, inversedBy="historiqueStatus")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demande;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $donnees = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemande(): ?DemandeIntervention
    {
        return $this->demande;
    }

    public function setDemande(?DemandeIntervention $demande): self
    {
        $this->demande = $demande;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusLibelle(): ?string
    {
        // On récupère la machine à état
        $mae = $this->getDemande()->getMachineEtat();
        $className = $this->status;
        $donnees = $this->getDonnees();

        // Si les données sont null, on doit hydrater un tableau vide
        if ($donnees === null) {
            $donnees = [];
        }

        // Si la classe de l'état existe bien, alors on l'instancie et l'on renvoi le libellé
        if (get_class_methods($className) !== null) {
            /** @var Etat $etat */
            $etat = new $className($mae);
            $etat->hydraterDonnees($donnees);
            return $etat->getLibelle();
        }

        // Sinon on renvoi le nom de la classe brut
        return $className;
    }

    public function getDonnees(): ?array
    {
        return $this->donnees;
    }

    public function setDonnees(?array $donnees): self
    {
        $this->donnees = $donnees;

        return $this;
    }
}
