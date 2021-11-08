<?php

namespace App\Entity;

use App\Entity\Interfaces\HistorisableInterface;
use App\Entity\References\GridMep;
use App\Entity\References\StatutMep;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\MepSsiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=MepSsiRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class MepSsi implements HistorisableInterface
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $palier;

    /**
     * @ORM\ManyToMany(targetEntity=Composant::class, inversedBy="mepSsis")
     * @ORM\OrderBy({"label" = "ASC"})
     * @Assert\Unique
     */
    private $composants;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $visibilite;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $equipe;

    /**
     * @ORM\ManyToMany(targetEntity=Pilote::class)
     * @ORM\OrderBy({"nom" = "ASC", "prenom" = "ASC"})
     * @Assert\Unique
     */
    private $pilotes;

    /**
     * @ORM\ManyToMany(targetEntity=DemandeIntervention::class, inversedBy="mepSsis")
     * @ORM\OrderBy({"numero" = "ASC"})
     * @Assert\Unique
     */
    private $demandesInterventions;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lep;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $mepDebut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $mepFin;

    /**
     * @ORM\Column(type="datetime")
     */
    private $mes;

    /**
     * @ORM\ManyToMany(targetEntity=GridMep::class, inversedBy="mepSsis")
     * @ORM\OrderBy({"label" = "ASC"})
     */
    private $grids;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $impacts;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $risques;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $motsClefs;

    /**
     * @ORM\ManyToOne(targetEntity=StatutMep::class, inversedBy="mepSsis")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statut;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $demandePar;

    public function __construct()
    {
        $this->composants = new ArrayCollection();
        $this->pilotes = new ArrayCollection();
        $this->demandesInterventions = new ArrayCollection();
        $this->grids = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPalier(): ?string
    {
        return $this->palier;
    }

    public function setPalier(string $palier): self
    {
        $this->palier = $palier;

        return $this;
    }

    /**
     * @return Collection|Composant[]
     */
    public function getComposants(): Collection
    {
        return $this->composants;
    }

    public function addComposant(Composant $composant): self
    {
        if (!$this->composants->contains($composant)) {
            $this->composants[] = $composant;
        }

        return $this;
    }

    public function removeComposant(Composant $composant): self
    {
        if ($this->composants->contains($composant)) {
            $this->composants->removeElement($composant);
        }

        return $this;
    }

    public function getVisibilite(): ?string
    {
        return $this->visibilite;
    }

    public function setVisibilite(string $visibilite): self
    {
        $this->visibilite = $visibilite;

        return $this;
    }

    public function getEquipe(): ?Service
    {
        return $this->equipe;
    }

    public function setEquipe(?Service $equipe): self
    {
        $this->equipe = $equipe;

        return $this;
    }

    /**
     * @return Collection|Pilote[]
     */
    public function getPilotes(): Collection
    {
        return $this->pilotes;
    }

    public function addPilote(Pilote $pilote): self
    {
        if (!$this->pilotes->contains($pilote)) {
            $this->pilotes[] = $pilote;
        }

        return $this;
    }

    public function removePilote(Pilote $pilote): self
    {
        if ($this->pilotes->contains($pilote)) {
            $this->pilotes->removeElement($pilote);
        }

        return $this;
    }

    /**
     * @return Collection|DemandeIntervention[]
     */
    public function getDemandesInterventions(): Collection
    {
        return $this->demandesInterventions;
    }

    public function addDemandesIntervention(DemandeIntervention $demandesIntervention): self
    {
        if (!$this->demandesInterventions->contains($demandesIntervention)) {
            $this->demandesInterventions[] = $demandesIntervention;
        }

        return $this;
    }

    public function removeDemandesIntervention(DemandeIntervention $demandesIntervention): self
    {
        if ($this->demandesInterventions->contains($demandesIntervention)) {
            $this->demandesInterventions->removeElement($demandesIntervention);
        }

        return $this;
    }

    public function getLep(): ?\DateTimeInterface
    {
        return $this->lep;
    }

    public function setLep(?\DateTimeInterface $lep): self
    {
        $this->lep = $lep;

        return $this;
    }

    public function getMepDebut(): ?\DateTimeInterface
    {
        return $this->mepDebut;
    }

    public function setMepDebut(?\DateTimeInterface $mepDebut): self
    {
        $this->mepDebut = $mepDebut;

        return $this;
    }

    public function getMepFin(): ?\DateTimeInterface
    {
        return $this->mepFin;
    }

    public function setMepFin(?\DateTimeInterface $mepFin): self
    {
        $this->mepFin = $mepFin;

        return $this;
    }

    public function getMes(): ?\DateTimeInterface
    {
        return $this->mes;
    }

    public function setMes(?\DateTimeInterface $mes): self
    {
        $this->mes = $mes;

        return $this;
    }

    /**
     * @return Collection|GridMep[]
     */
    public function getGrids(): Collection
    {
        return $this->grids;
    }

    public function addGrid(GridMep $gridMep): self
    {
        if (!$this->grids->contains($gridMep)) {
            $this->grids[] = $gridMep;
        }

        return $this;
    }

    public function removeGrid(GridMep $gridMep): self
    {
        if ($this->grids->contains($gridMep)) {
            $this->grids->removeElement($gridMep);
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getImpacts(): ?string
    {
        return $this->impacts;
    }

    public function setImpacts(?string $impacts): self
    {
        $this->impacts = $impacts;

        return $this;
    }

    public function getRisques(): ?string
    {
        return $this->risques;
    }

    public function setRisques(?string $risques): self
    {
        $this->risques = $risques;

        return $this;
    }

    public function getMotsClefs(): ?string
    {
        return $this->motsClefs;
    }

    public function setMotsClefs(?string $motsClefs): self
    {
        $this->motsClefs = $motsClefs;

        return $this;
    }

    public function getStatut(): ?StatutMep
    {
        return $this->statut;
    }

    public function getStatutLabel(): ?string
    {
        switch ($this->statut->getLabel()) {
            case "PROJET":
                return "Projet";
            case "CONFIRME":
                return "Confirmé";
            case "ARCHIVE":
                return "Archivé";
            case "ERREUR":
                return "Erreur";
        }
        return null;
    }

    public function setStatut(?StatutMep $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDemandePar(): ?Service
    {
        return $this->demandePar;
    }

    public function setDemandePar(?Service $demandePar): self
    {
        $this->demandePar = $demandePar;

        return $this;
    }
}
