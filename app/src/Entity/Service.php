<?php

namespace App\Entity;

use App\Entity\Composant\Annuaire;
use App\Entity\Fiabilisation\DemandeReferentielFlux;
use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\Interfaces\HistorisableInterface;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ServiceRepository;
use App\Entity\Traits\HorodatageTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass=ServiceRepository::class)
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(
 *  fields={"label"},
 *  message="service.label.unique",
 *  repositoryMethod="libelleServiceDejaUtilise"
 * )
 */
class Service implements UserInterface, HistorisableInterface
{
    use HorodatageTrait;

    // Constantes de configuration de la génération de mot de passe
    private const MOTDEPASSE_SYMBOLES = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    private const MOTDEPASSE_LONGUEUR_MIN = 16;
    private const MOTDEPASSE_LONGUEUR_MAX = 30;

    // Roles disponibles
    /**
     * ROLE_ADMIN       => ROLE_GESTION
     * ROLE_DME         => ROLE_GESTION
     * ROLE_GESTION     => ROLE_UTILISATEUR
     * ROLE_INTERVENANT => ROLE_UTILISATEUR
     * ROLE_INVITE      => (/)
     */
    public const ROLE_ADMIN         = 'ROLE_ADMIN';         // Administrateur
    public const ROLE_DME           = 'ROLE_DME';           // Dme
    public const ROLE_GESTION       = 'ROLE_GESTION';       // Gestion
    public const ROLE_INTERVENANT   = 'ROLE_INTERVENANT';   // Intervenant
    public const ROLE_UTILISATEUR   = 'ROLE_UTILISATEUR';   // Utilisateur
    public const ROLE_INVITE        = 'ROLE_INVITE';        // Invité
    public const ROLE_USURPATEUR    = 'ROLE_USURPATEUR';    // Role supplémentaire permettant d'usurper l'identité d'un autre utilisateur

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups("basique")
     */
    private $id;

    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=64, unique=true)
     * @Groups("basique")
     * @Assert\NotBlank
     * @Assert\Length(
     *  min = 3,
     *  max = 64,
     *  minMessage = "service.label.min",
     *  maxMessage = "service.label.max",
     *  allowEmptyString = false
     * )
     */
    private $label;

    /**
     * @Assert\NotBlank
     * @Assert\Email
     * @ORM\Column(type="string", length=128)
     * @Groups("basique")
     * @Assert\NotBlank
     * @Assert\Length(
     *  max = 128,
     *  maxMessage = "service.email.max",
     *  allowEmptyString = false
     * )
     */
    private $email;

    /**
     * @Assert\NotBlank
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $motdepasse;

    /**
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $resetMotdepasse = false;

    /**
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $estServiceExploitant = false;

    /**
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $estBureauRattachement = false;

    /**
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $estStructureRattachement = false;

    /**
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $estPilotageDme = false;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class)
     */
    private $structurePrincipale;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

    /**
     * @ORM\OneToMany(targetEntity=Pilote::class, mappedBy="equipe")
     */
    private $pilotes;

    /**
     * @ORM\OneToMany(targetEntity=Composant::class, mappedBy="bureauRattachement")
     */
    private $bureauRattachementComposants;

    /**
     * @ORM\OneToMany(targetEntity=DemandeReferentielFlux::class, mappedBy="serviceDemandeur")
     */
    private $demandesReferentielFlux;

    /**
     * @ORM\OneToMany(targetEntity=DemandePerimetreApplicatif::class, mappedBy="serviceDemandeur")
     */
    private $demandesPerimetreApplicatif;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateValidationBalf;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateDerniereSollicitation;

    /**
     * @ORM\OneToMany(targetEntity=DemandeIntervention::class, mappedBy="demandePar")
     */
    private $demandesIntervention;

    /**
     * @ORM\OneToMany(targetEntity=Annuaire::class, mappedBy="service")
     */
    private $annuaire;

    /**
     * @ORM\OneToMany(targetEntity=Composant::class, mappedBy="exploitant")
     */
    private $composantsExploitant;

    /**
     * @ORM\OneToMany(targetEntity=Composant::class, mappedBy="equipe")
     */
    private $composantsEquipe;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentite::class, mappedBy="service")
     */
    private $carteIdentites;

    /**
     * @ORM\OneToMany(targetEntity=CarteIdentiteEvenement::class, mappedBy="service")
     */
    private $carteIdentiteEvenements;

    public function __construct()
    {
        $this->pilotes = new ArrayCollection();
        $this->bureauRattachementComposants = new ArrayCollection();
        $this->demandesReferentielFlux = new ArrayCollection();
        $this->demandesPerimetreApplicatif = new ArrayCollection();
        $this->demandesIntervention = new ArrayCollection();
        $this->annuaire = new ArrayCollection();
        $this->composantsExploitant = new ArrayCollection();
        $this->composantsEquipe = new ArrayCollection();
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getMotdepasse(): ?string
    {
        return $this->motdepasse;
    }

    public function setMotdepasse(?string $motdepasse): self
    {
        $this->motdepasse = $motdepasse;

        return $this;
    }

    public static function motdepasseValidation(): array
    {
        return [

            new Assert\Regex([
                'pattern' => '/^.{0,30}$/',
                'message' => 'motdepasse.troplong',]),
            new Assert\Regex([
                'pattern' => '/.{6,}/',
                'message' => 'motdepasse.tropcourt',]),

            new Assert\Regex([
                'pattern' => '/^[0-9-a-z-A-Z]{0,}$/',
                'message' => 'motdepasse.caracterespecial',])
            ];
    }

    public function getResetMotdepasse(): ?bool
    {
        return $this->resetMotdepasse;
    }

    public function setResetMotdepasse(bool $resetMotdepasse): self
    {
        $this->resetMotdepasse = $resetMotdepasse;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->motdepasse;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // pas nécessaire avec l'Algorithme "bcrypt" algorithm in security.yaml
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->label;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * Retourne un mot de passe à partir de la liste des symboles utilisable
     */
    public static function generationMotdepasse(): string
    {
        $motdepasse = '';
        $longueur = rand(self::MOTDEPASSE_LONGUEUR_MIN, self::MOTDEPASSE_LONGUEUR_MAX);
        $nombreSymboles = strlen(self::MOTDEPASSE_SYMBOLES);
        for ($i = 0; $i < $longueur; $i++) {
            $motdepasse .= substr(self::MOTDEPASSE_SYMBOLES, rand(0, $nombreSymboles - 1), 1);
        }
        return $motdepasse;
    }

    public function getEstServiceExploitant(): ?bool
    {
        return $this->estServiceExploitant;
    }

    public function setEstServiceExploitant(bool $estServiceExploitant): self
    {
        $this->estServiceExploitant = $estServiceExploitant;

        return $this;
    }

    public function getEstBureauRattachement(): ?bool
    {
        return $this->estBureauRattachement;
    }

    public function setEstBureauRattachement(bool $estBureauRattachement): self
    {
        $this->estBureauRattachement = $estBureauRattachement;

        return $this;
    }

    public function getEstStructureRattachement(): ?bool
    {
        return $this->estStructureRattachement;
    }

    public function setEstStructureRattachement(bool $estStructureRattachement): self
    {
        $this->estStructureRattachement = $estStructureRattachement;

        return $this;
    }

    public function getEstPilotageDme(): ?bool
    {
        return $this->estPilotageDme;
    }

    public function setEstPilotageDme(bool $estPilotageDme): self
    {
        $this->estPilotageDme = $estPilotageDme;

        return $this;
    }

    public function getStructurePrincipale(): ?self
    {
        return $this->structurePrincipale;
    }

    public function setStructurePrincipale(?self $service): self
    {
        $this->structurePrincipale = $service;
        return $this;
    }

    public function getSupprimeLe(): ?\DateTimeInterface
    {
        return $this->supprimeLe;
    }

    public function setSupprimeLe(?\DateTimeInterface $supprimeLe): self
    {
        $this->supprimeLe = $supprimeLe;

        return $this;
    }

    /**
     * Retourne la liste des roles existants
     */
    public static function listeRoles()
    {
        return [self::ROLE_ADMIN, self::ROLE_DME, self::ROLE_INTERVENANT, self::ROLE_INVITE];
    }

    /**
     * Retourne vrai si le service peut usurper l'identité d'un autre utilisateur
     */
    public function getEstRoleUsurpateur(): bool
    {
        return in_array(self::ROLE_USURPATEUR, $this->getRoles());
    }
    public function setEstRoleUsurpateur(bool $estRoleUsurpateur): self
    {
        $roles = $this->getRoles();
        $roleUsurpateurIndex = array_search(self::ROLE_USURPATEUR, $roles);
        if (($roleUsurpateurIndex !== false) !== $estRoleUsurpateur) {
            if ($estRoleUsurpateur) {
                $roles[] = self::ROLE_USURPATEUR;
            } else {
                $roles = array_splice($roles, $roleUsurpateurIndex, 1);
            }
            $this->setRoles($roles);
        }
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
            $pilote->setEquipe($this);
        }

        return $this;
    }

    public function removePilote(Pilote $pilote): self
    {
        if ($this->pilotes->contains($pilote)) {
            $this->pilotes->removeElement($pilote);
            // set the owning side to null (unless already changed)
            if ($pilote->getEquipe() === $this) {
                $pilote->setEquipe(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Composant[]
     */
    public function getBureauRattachementComposants(): Collection
    {
        return $this->bureauRattachementComposants;
    }

    public function addBureauRattachementComposant(Composant $bureauRattachementComposant): self
    {
        if (!$this->bureauRattachementComposants->contains($bureauRattachementComposant)) {
            $this->bureauRattachementComposants[] = $bureauRattachementComposant;
            $bureauRattachementComposant->setBureauRattachement($this);
        }

        return $this;
    }

    public function removeBureauRattachementComposant(Composant $bureauRattachementComposant): self
    {
        if ($this->bureauRattachementComposants->contains($bureauRattachementComposant)) {
            $this->bureauRattachementComposants->removeElement($bureauRattachementComposant);
            // set the owning side to null (unless already changed)
            if ($bureauRattachementComposant->getBureauRattachement() === $this) {
                $bureauRattachementComposant->setBureauRattachement(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }

    /**
     * @return Collection|DemandeReferentielFlux[]
     */
    public function getDemandesReferentielFlux(): Collection
    {
        return $this->demandesReferentielFlux;
    }

    public function addDemandesReferentielFlux(DemandeReferentielFlux $demandesReferentielFlux): self
    {
        if (!$this->demandesReferentielFlux->contains($demandesReferentielFlux)) {
            $this->demandesReferentielFlux[] = $demandesReferentielFlux;
            $demandesReferentielFlux->setServiceDemandeur($this);
        }

        return $this;
    }

    public function removeDemandesReferentielFlux(DemandeReferentielFlux $demandesReferentielFlux): self
    {
        if ($this->demandesReferentielFlux->contains($demandesReferentielFlux)) {
            $this->demandesReferentielFlux->removeElement($demandesReferentielFlux);
            // set the owning side to null (unless already changed)
            if ($demandesReferentielFlux->getServiceDemandeur() === $this) {
                $demandesReferentielFlux->setServiceDemandeur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DemandePerimetreApplicatif[]
     */
    public function getDemandesPerimetreApplicatif(): Collection
    {
        return $this->demandesPerimetreApplicatif;
    }

    public function addDemandesPerimetreApplicatif(DemandePerimetreApplicatif $demandesPerimetreApplicatif): self
    {
        if (!$this->demandesPerimetreApplicatif->contains($demandesPerimetreApplicatif)) {
            $this->demandesPerimetreApplicatif[] = $demandesPerimetreApplicatif;
            $demandesPerimetreApplicatif->setServiceDemandeur($this);
        }

        return $this;
    }

    public function removeDemandesPerimetreApplicatif(DemandePerimetreApplicatif $demandesPerimetreApplicatif): self
    {
        if ($this->demandesPerimetreApplicatif->contains($demandesPerimetreApplicatif)) {
            $this->demandesPerimetreApplicatif->removeElement($demandesPerimetreApplicatif);
            // set the owning side to null (unless already changed)
            if ($demandesPerimetreApplicatif->getServiceDemandeur() === $this) {
                $demandesPerimetreApplicatif->setServiceDemandeur(null);
            }
        }

        return $this;
    }

    public function getDateValidationBalf(): ?\DateTimeInterface
    {
        return $this->dateValidationBalf;
    }

    public function setDateValidationBalf(?\DateTimeInterface $dateValidationBalf): self
    {
        $this->dateValidationBalf = $dateValidationBalf;

        return $this;
    }

    public function validerBalf(): self
    {
        $this->setDateValidationBalf(new \DateTime());
        return $this;
    }

    public function getDateDerniereSollicitation(): ?\DateTimeInterface
    {
        return $this->dateDerniereSollicitation;
    }

    public function getDateDerniereSollicitationAffichage(): ?string
    {
        if ($this->dateDerniereSollicitation instanceof \DateTime) {
            return $this->dateDerniereSollicitation->format('d/m/Y H:i:s');
        }

        return null;
    }

    public function setDateDerniereSollicitationAffichage(?\DateTimeInterface $dateDerniereSollicitation): self
    {
        $this->dateDerniereSollicitation = $dateDerniereSollicitation;

        return $this;
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
            $demandesIntervention->setDemandePar($this);
        }

        return $this;
    }

    public function removeDemandesIntervention(DemandeIntervention $demandesIntervention): self
    {
        if ($this->demandesIntervention->contains($demandesIntervention)) {
            $this->demandesIntervention->removeElement($demandesIntervention);
            // set the owning side to null (unless already changed)
            if ($demandesIntervention->getDemandePar() === $this) {
                $demandesIntervention->setDemandePar(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Annuaire[]
     */
    public function getAnnuaire(): Collection
    {
        return $this->annuaire;
    }

    public function addAnnuaire(Annuaire $annuaire): self
    {
        if (!$this->annuaire->contains($annuaire)) {
            $this->annuaire[] = $annuaire;
            $annuaire->setService($this);
            $this->updateTimestamps();
        }

        return $this;
    }

    public function removeAnnuaire(Annuaire $annuaire): self
    {
        if ($this->annuaire->contains($annuaire)) {
            $this->annuaire->removeElement($annuaire);
            if ($annuaire->getService() === $this && $annuaire->getSupprimeLe() === null) {
                $annuaire->setSupprimeLe(new \DateTime());
            }
            $this->updateTimestamps();
        }

        return $this;
    }

    /**
     * @return Collection|Composant[]
     */
    public function getComposantsExploitant(): Collection
    {
        return $this->composantsExploitant;
    }

    public function addComposantsExploitant(Composant $composant): self
    {
        if (!$this->composantsExploitant->contains($composant)) {
            $this->composantsExploitant[] = $composant;
            $composant->setExploitant($this);
            $this->updateTimestamps();
        }

        return $this;
    }

    public function removeComposantsExploitant(Composant $composant): self
    {
        if ($this->composantsExploitant->contains($composant)) {
            $this->composantsExploitant->removeElement($composant);
            if ($composant->getExploitant() === $this) {
                $composant->setExploitant(null);
            }
            $this->updateTimestamps();
        }

        return $this;
    }

    /**
     * @return Collection|Composant[]
     */
    public function getComposantsEquipe(): Collection
    {
        return $this->composantsEquipe;
    }

    public function addComposantsEquipe(Composant $composant): self
    {
        if (!$this->composantsEquipe->contains($composant)) {
            $this->composantsEquipe[] = $composant;
            $composant->setEquipe($this);
            $this->updateTimestamps();
        }

        return $this;
    }

    public function removeComposantsEquipe(Composant $composant): self
    {
        if ($this->composantsEquipe->contains($composant)) {
            $this->composantsEquipe->removeElement($composant);
            if ($composant->getEquipe() === $this) {
                $composant->setEquipe(null);
            }
            $this->updateTimestamps();
        }

        return $this;
    }

    /** Permet d'utiliser array_column pour la propriété id */
    public function __get($prop)
    {
        return ($prop === 'id') ? $this->getId() : null;
    }
    public function __isset($prop) : bool
    {
        return ($prop === 'id') ? isset($this->id) : false;
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
            $carteIdentite->setService($this);
        }

        return $this;
    }

    public function removeCarteIdentite(CarteIdentite $carteIdentite): self
    {
        if ($this->carteIdentites->contains($carteIdentite)) {
            $this->carteIdentites->removeElement($carteIdentite);
            // set the owning side to null (unless already changed)
            if ($carteIdentite->getService() === $this) {
                $carteIdentite->setService(null);
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
            $carteIdentiteEvenement->setService($this);
        }

        return $this;
    }

    public function removeCarteIdentiteEvenement(CarteIdentiteEvenement $carteIdentiteEvenement): self
    {
        if ($this->carteIdentiteEvenements->contains($carteIdentiteEvenement)) {
            $this->carteIdentiteEvenements->removeElement($carteIdentiteEvenement);
            // set the owning side to null (unless already changed)
            if ($carteIdentiteEvenement->getService() === $this) {
                $carteIdentiteEvenement->setService(null);
            }
        }

        return $this;
    }
}
