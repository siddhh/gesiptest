<?php

namespace App\Entity;

use App\Entity\Composant\Annuaire;
use App\Entity\Composant\PlageUtilisateur;
use App\Entity\GenericComposantInterface;
use App\Entity\Interfaces\HistorisableInterface;
use App\Entity\Meteo\Evenement;
use App\Entity\References\Domaine;
use App\Entity\References\TypeElement;
use App\Entity\References\Usager;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\ComposantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ComposantRepository::class)
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(
 *  fields={"label"},
 *  message="composant.label.unique",
 *  repositoryMethod="libelleComposantDejaUtilise"
 * )
 */
class Composant implements HistorisableInterface, GenericComposantInterface
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
     * @Assert\NotBlank
     * @Assert\Length(
     *  min = 1,
     *  max = 255,
     *  minMessage = "composant.label.min",
     *  maxMessage = "composant.label.max",
     *  allowEmptyString = false
     * )
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $codeCarto;

    /**
     * @ORM\ManyToOne(targetEntity=Usager::class, inversedBy="composants")
     * @ORM\JoinColumn(nullable=false)
     */
    private $usager;

    /**
     * @ORM\ManyToOne(targetEntity=Domaine::class, inversedBy="composants")
     */
    private $domaine;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $intitulePlageUtilisateur;

    /**
     * @ORM\OneToMany(targetEntity=PlageUtilisateur::class, mappedBy="composant", cascade={"persist", "remove"})
     * @ORM\OrderBy({"jour" = "ASC", "debut" = "ASC"})
     */
    private $plagesUtilisateur;

    /**
     * @ORM\Column(type="integer")
     */
    private $dureePlageUtilisateur;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="composantsExploitant")
     */
    private $exploitant;

    /**
     * @ORM\Column(type="boolean")
     */
    private $meteoActive;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="composantsEquipe")
     */
    private $equipe;

    /**
     * @ORM\ManyToOne(targetEntity=Pilote::class, inversedBy="composants")
     */
    private $pilote;

    /**
     * @ORM\ManyToOne(targetEntity=Pilote::class, inversedBy="suppleantComposants")
     */
    private $piloteSuppleant;

    /**
     * @ORM\ManyToOne(targetEntity=TypeElement::class, inversedBy="composants")
     * @ORM\JoinColumn(nullable=false)
     */
    private $typeElement;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $estSiteHebergement;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="bureauRattachementComposants")
     */
    private $bureauRattachement;

    /**
     * @ORM\OneToMany(targetEntity=Annuaire::class, mappedBy="composant", cascade={"persist", "remove"})
     */
    private $annuaire;

    /**
     * @ORM\ManyToMany(targetEntity=Composant::class, inversedBy="impactesParComposants")
     * @ORM\OrderBy({"label" = "ASC"})
     */
    private $composantsImpactes;

    /**
     * @ORM\ManyToMany(targetEntity=Composant::class, mappedBy="composantsImpactes")
     * @ORM\OrderBy({"label" = "ASC"})
     */
    private $impactesParComposants;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $archiveLe;

    /**
     * @ORM\OneToMany(targetEntity=DemandeIntervention::class, mappedBy="composant")
     */
    private $demandesIntervention;

    /**
     * @ORM\OneToMany(targetEntity=Evenement::class, mappedBy="composant")
     */
    private $evenementsMeteo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $codeCartoId;

    /**
     * @ORM\ManyToMany(targetEntity=MepSsi::class, mappedBy="composants")
     */
    private $mepSsis;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentite::class, mappedBy="composant")
     * @ORM\OrderBy({"ajouteLe" = "DESC"})
     */
    private $carteIdentites;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentiteEvenement::class, mappedBy="composant")
     */
    private $carteIdentiteEvenements;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    public function __construct()
    {
        $this->plagesUtilisateur = new ArrayCollection();
        $this->annuaire = new ArrayCollection();
        $this->composantsImpactes = new ArrayCollection();
        $this->impactesParComposants = new ArrayCollection();
        $this->dureePlageUtilisateur = 0;
        $this->demandesIntervention = new ArrayCollection();
        $this->evenementsMeteo = new ArrayCollection();
        $this->mepSsis = new ArrayCollection();
        $this->carteIdentites = new ArrayCollection();
        $this->carteIdentiteEvenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCodeCarto(): ?string
    {
        return $this->codeCarto;
    }

    public function setCodeCarto(?string $codeCarto): self
    {
        $this->codeCarto = $codeCarto;

        return $this;
    }

    public function getUsager(): ?Usager
    {
        return $this->usager;
    }

    public function setUsager(?Usager $usager): self
    {
        $this->usager = $usager;

        return $this;
    }

    public function getDomaine(): ?Domaine
    {
        return $this->domaine;
    }

    public function setDomaine(?Domaine $domaine): self
    {
        $this->domaine = $domaine;

        return $this;
    }

    public function getIntitulePlageUtilisateur(): ?string
    {
        return $this->intitulePlageUtilisateur;
    }

    public function setIntitulePlageUtilisateur(?string $intitulePlageUtilisateur): self
    {
        $this->intitulePlageUtilisateur = $intitulePlageUtilisateur;

        return $this;
    }

    /**
     * @return Collection|PlageUtilisateur[]
     */
    public function getPlagesUtilisateur(): Collection
    {
        return $this->plagesUtilisateur;
    }

    public function addPlagesUtilisateur(PlageUtilisateur $plagesUtilisateur): self
    {
        if (!$this->plagesUtilisateur->contains($plagesUtilisateur)) {
            $this->plagesUtilisateur[] = $plagesUtilisateur;
            $plagesUtilisateur->setComposant($this);
            $this->dureePlageUtilisateur += $plagesUtilisateur->getTempsTotalEnMinutes();
        }

        return $this;
    }

    public function removePlagesUtilisateur(PlageUtilisateur $plagesUtilisateur): self
    {
        if ($this->plagesUtilisateur->contains($plagesUtilisateur)) {
            $this->plagesUtilisateur->removeElement($plagesUtilisateur);
            $this->dureePlageUtilisateur -= $plagesUtilisateur->getTempsTotalEnMinutes();
            // set the owning side to null (unless already changed)
            if ($plagesUtilisateur->getComposant() === $this) {
                $plagesUtilisateur->setComposant(null);
            }
        }

        return $this;
    }

    /**
     * @return PlageUtilisateur[]
     */
    public function getPlagesUtilisateurViaUsage(): array
    {
        // On récupère les plages d'utilisation du composant
        $plagesUtilisateurs = $this->getPlagesUtilisateur()->toArray();

        // Si cette plage est vide (et qu'un usager est défini pour ce composant)
        if (count($this->getPlagesUtilisateur()) === 0 && $this->getUsager() !== null) {
            // On récupère le label de l'usager
            $usager = $this->getUsager()->getLabel();

            // Et si les usagers utilisateurs sont "Externe" ou "Mixte"
            if (strpos($usager, 'Externe') !== false || strpos($usager, 'Mixte') !== false) {
                // Alors la plage est 24h/24 7j/7
                for ($i = 1; $i <= 7; $i++) {
                    $plagesUtilisateurs[] = (new PlageUtilisateur())
                        ->setJour($i)
                        ->setDebut(\DateTime::createFromFormat('H:i:s', '00:00:00'))
                        ->setFin(\DateTime::createFromFormat('H:i:s', '23:59:59'));
                }

            // Et si les usagers utilisateurs sont "Interne", alors la plage est 8h/18h 5j/7
            } elseif (strpos($usager, 'Interne') !== false) {
                // Alors la plage est 8h => 18h du lundi au vendredi
                for ($i = 1; $i <= 5; $i++) {
                    $plagesUtilisateurs[] = (new PlageUtilisateur())
                        ->setJour($i)
                        ->setDebut(\DateTime::createFromFormat('H:i:s', '08:00:00'))
                        ->setFin(\DateTime::createFromFormat('H:i:s', '18:00:00'));
                }
            }
        }

        // On renvoi les plages d'utilisation en fonction de l'usage défini
        return $plagesUtilisateurs;
    }

    public function getDureePlageUtilisateur(): ?int
    {
        return $this->dureePlageUtilisateur;
    }

    public function setDureePlageUtilisateur(int $dureePlageUtilisateur): self
    {
        $this->dureePlageUtilisateur = $dureePlageUtilisateur;

        return $this;
    }

    public function getExploitant(): ?Service
    {
        return $this->exploitant;
    }

    public function setExploitant(?Service $exploitant): self
    {
        $this->exploitant = $exploitant;

        return $this;
    }

    public function getMeteoActive(): ?bool
    {
        return $this->meteoActive;
    }

    public function setMeteoActive(bool $meteoActive): self
    {
        $this->meteoActive = $meteoActive;

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

    public function getPilote(): ?Pilote
    {
        return $this->pilote;
    }

    public function setPilote(?Pilote $pilote): self
    {
        $this->pilote = $pilote;

        return $this;
    }

    public function getPiloteSuppleant(): ?Pilote
    {
        return $this->piloteSuppleant;
    }

    public function setPiloteSuppleant(?Pilote $piloteSuppleant): self
    {
        $this->piloteSuppleant = $piloteSuppleant;

        return $this;
    }

    public function getTypeElement(): ?TypeElement
    {
        return $this->typeElement;
    }

    public function setTypeElement(?TypeElement $typeElement): self
    {
        $this->typeElement = $typeElement;

        return $this;
    }

    public function getEstSiteHebergement(): ?bool
    {
        return $this->estSiteHebergement;
    }

    public function setEstSiteHebergement(?bool $estSiteHebergement): self
    {
        $this->estSiteHebergement = $estSiteHebergement;

        return $this;
    }

    public function getBureauRattachement(): ?Service
    {
        return $this->bureauRattachement;
    }

    public function setBureauRattachement(?Service $bureauRattachement): self
    {
        $this->bureauRattachement = $bureauRattachement;

        return $this;
    }

    /**
     * @param bool $avecSuppression
     *
     * @return Collection|Annuaire[]
     */
    public function getAnnuaire(bool $avecSuppression = true): Collection
    {
        if (!$avecSuppression) {
            return new ArrayCollection($this->annuaire->filter(function ($a) {
                return $a->getSupprimeLe() === null;
            })->getValues());
        }
        return $this->annuaire;
    }

    public function addAnnuaire(Annuaire $annuaire): self
    {
        if (!$this->annuaire->contains($annuaire)) {
            $this->annuaire[] = $annuaire;
            $annuaire->setComposant($this);
            $this->updateTimestamps();
        }

        return $this;
    }

    public function removeAnnuaire(Annuaire $annuaire): self
    {
        if ($this->annuaire->contains($annuaire)) {
            $this->annuaire->removeElement($annuaire);
            if ($annuaire->getComposant() === $this && $annuaire->getSupprimeLe() === null) {
                $annuaire->setSupprimeLe(new \DateTime());
            }
            $this->updateTimestamps();
        }

        return $this;
    }

    /**
     * Flux sortants = Composants QUI IMPACTE notre composant (possibilité de d'ajouter ou non les composants archivés)
     *
     * @param bool $avecArchive
     *
     * @return Collection|self[]
     */
    public function getFluxEntrants(bool $avecArchive = true): Collection
    {
        if (!$avecArchive) {
            return $this->impactesParComposants->filter(function ($c) {
                return ($c->getArchiveLe() === null);
            });
        }
        return $this->impactesParComposants;
    }

    /**
     * Flux sortants = Composants IMPACTÉS par notre composant (possibilité de d'ajouter ou non les composants archivés)
     *
     * @param bool $avecArchive
     *
     * @return Collection|self[]
     */
    public function getFluxSortants(bool $avecArchive = true): Collection
    {
        if (!$avecArchive) {
            return $this->composantsImpactes->filter(function ($c) {
                return ($c->getArchiveLe() === null);
            });
        }
        return $this->composantsImpactes;
    }

    /**
     * @return Collection|self[]
     */
    public function getComposantsImpactes(): Collection
    {
        return $this->composantsImpactes;
    }

    public function addComposantsImpacte(self $composantsImpacte): self
    {
        if (!$this->composantsImpactes->contains($composantsImpacte)) {
            $this->composantsImpactes[] = $composantsImpacte;
            $this->updateTimestamps();
        }

        return $this;
    }

    public function removeComposantsImpacte(self $composantsImpacte): self
    {
        if ($this->composantsImpactes->contains($composantsImpacte)) {
            $this->composantsImpactes->removeElement($composantsImpacte);
            $this->updateTimestamps();
        }

        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getImpactesParComposants(): Collection
    {
        return $this->impactesParComposants;
    }

    public function addImpactesParComposant(self $impactesParComposant): self
    {
        if (!$this->impactesParComposants->contains($impactesParComposant)) {
            $this->impactesParComposants[] = $impactesParComposant;
            $impactesParComposant->addComposantsImpacte($this);
            $this->updateTimestamps();
        }

        return $this;
    }

    public function removeImpactesParComposant(self $impactesParComposant): self
    {
        if ($this->impactesParComposants->contains($impactesParComposant)) {
            $this->impactesParComposants->removeElement($impactesParComposant);
            $impactesParComposant->removeComposantsImpacte($this);
            $this->updateTimestamps();
        }

        return $this;
    }

    public function getArchiveLe(): ?\DateTimeInterface
    {
        return $this->archiveLe;
    }

    public function setArchiveLe(?\DateTimeInterface $archiveLe): self
    {
        $this->archiveLe = $archiveLe;

        return $this;
    }

    public function estArchive(): bool
    {
        return ($this->archiveLe !== null);
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }

    /**
     * @return Collection|DemandeIntervention[]
     */
    public function getDemandesIntervention(): Collection
    {
        return $this->demandesIntervention;
    }

    public function addDemandesIntervention(DemandeIntervention $demandesIntervention): self
    {
        if (!$this->demandesIntervention->contains($demandesIntervention)) {
            $this->demandesIntervention[] = $demandesIntervention;
            $demandesIntervention->setComposant($this);
        }

        return $this;
    }

    public function removeDemandesIntervention(DemandeIntervention $demandesIntervention): self
    {
        if ($this->demandesIntervention->contains($demandesIntervention)) {
            $this->demandesIntervention->removeElement($demandesIntervention);
            // set the owning side to null (unless already changed)
            if ($demandesIntervention->getComposant() === $this) {
                $demandesIntervention->setComposant(null);
            }
        }

        return $this;
    }

    /** Permet d'utiliser array_column pour la propriété id */
    public function __get($prop)
    {
        switch ($prop) {
            default:
                return null;
            case 'id':
                return $this->getId();
            case 'label':
                return $this->getLabel();
        }
    }
    public function __isset($prop) : bool
    {
        switch ($prop) {
            default:
                return false;
            case 'id':
                return isset($this->id);
            case 'label':
                return isset($this->label);
        }
    }

    /**
     * @return Collection|Evenement[]
     */
    public function getEvenementsMeteo(): Collection
    {
        return $this->evenementsMeteo;
    }

    public function getEvenementsMeteoParPeriode(\DateTime $periodeDebut, \DateTime $periodeFin) : Collection
    {
        return $this->getEvenementsMeteo()->filter(function (Evenement $evenement) use ($periodeDebut, $periodeFin) {
            return $evenement->getDebut() < $periodeFin && $evenement->getFin() > $periodeDebut;
        });
    }

    public function addEvenementsMeteo(Evenement $evenementsMeteo): self
    {
        if (!$this->evenementsMeteo->contains($evenementsMeteo)) {
            $this->evenementsMeteo[] = $evenementsMeteo;
            $evenementsMeteo->setComposant($this);
        }

        return $this;
    }

    public function removeEvenementsMeteo(Evenement $evenementsMeteo): self
    {
        if ($this->evenementsMeteo->contains($evenementsMeteo)) {
            $this->evenementsMeteo->removeElement($evenementsMeteo);
            // set the owning side to null (unless already changed)
            if ($evenementsMeteo->getComposant() === $this) {
                $evenementsMeteo->setComposant(null);
            }
        }

        return $this;
    }

    public function getCodeCartoId(): ?string
    {
        return $this->codeCartoId;
    }

    public function setCodeCartoId(?string $codeCartoId): self
    {
        $this->codeCartoId = $codeCartoId;

        return $this;
    }

    /**
     * @return Collection|MepSsi[]
     */
    public function getMepSsis(): Collection
    {
        return $this->mepSsis;
    }

    public function addMepSsi(MepSsi $mepSsi): self
    {
        if (!$this->mepSsis->contains($mepSsi)) {
            $this->mepSsis[] = $mepSsi;
            $mepSsi->addComposant($this);
        }

        return $this;
    }

    public function removeMepSsi(MepSsi $mepSsi): self
    {
        if ($this->mepSsis->contains($mepSsi)) {
            $this->mepSsis->removeElement($mepSsi);
            $mepSsi->removeComposant($this);
        }

        return $this;
    }

    /**
     * @return Collection|CarteIdentite[]
     */
    public function getCarteIdentites(): Collection
    {
        return $this->carteIdentites;
    }

    public function addCarteIdentite(CarteIdentite $carteIdentite): self
    {
        if (!$this->carteIdentites->contains($carteIdentite)) {
            $this->carteIdentites[] = $carteIdentite;
            $carteIdentite->setComposant($this);
        }

        return $this;
    }

    public function removeCarteIdentite(CarteIdentite $carteIdentite): self
    {
        if ($this->carteIdentites->contains($carteIdentite)) {
            $this->carteIdentites->removeElement($carteIdentite);
            // set the owning side to null (unless already changed)
            if ($carteIdentite->getComposant() === $this) {
                $carteIdentite->setComposant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CarteIdentiteEvenement[]
     */
    public function getCarteIdentiteEvenements(): Collection
    {
        return $this->carteIdentiteEvenements;
    }

    public function addCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self
    {
        if (!$this->carteIdentiteEvenements->contains($carteIdentiteEvenement)) {
            $this->carteIdentiteEvenements[] = $carteIdentiteEvenement;
            $carteIdentiteEvenement->setComposant($this);
        }

        return $this;
    }

    public function removeCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self
    {
        if ($this->carteIdentiteEvenements->contains($carteIdentiteEvenement)) {
            $this->carteIdentiteEvenements->removeElement($carteIdentiteEvenement);
            // set the owning side to null (unless already changed)
            if ($carteIdentiteEvenement->getComposant() === $this) {
                $carteIdentiteEvenement->setComposant(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
