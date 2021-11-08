<?php

namespace App\Entity;

use App\Entity\Interfaces\HistorisableInterface;
use App\Entity\Service;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\PiloteRepository;
use App\Utils\ChaineDeCaracteres;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Mime\Address;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass=PiloteRepository::class)
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(
 *     fields={"prenom","nom"},
 *     message="nomprenom.unique"
 * )
 * @UniqueEntity(
 *     fields={"balp"},
 *     message="balp.unique",
 *     repositoryMethod="BalpDejaUtilise"
 * )
 */
class Pilote implements HistorisableInterface
{
    use HorodatageTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=255)
     */
    private $prenom;

    /**
     * @Assert\Regex(
     *     pattern="/.dgfip.finances.gouv.fr$/",
     *     match=true,
     *     message="balp.domaine"
     * )
     * @Assert\Email(message = "Ce n'est pas une balp valide")
     * @Assert\NotBlank
     * @ORM\Column(type="string", length=255)
     */
    private $balp;

    /**
     * @Assert\NotBlank
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="pilotes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $equipe;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

    /**
     * @ORM\OneToMany(targetEntity=Composant::class, mappedBy="pilote")
     */
    private $composants;

    /**
     * @ORM\OneToMany(targetEntity=Composant::class, mappedBy="piloteSuppleant")
     */
    private $suppleantComposants;

    public function __construct()
    {
        $this->composants = new ArrayCollection();
        $this->suppleantComposants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $Nom): self
    {
        $this->nom = mb_strtoupper($Nom);

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $Prenom): self
    {
        $this->prenom = ChaineDeCaracteres::mbUcwords($Prenom);

        return $this;
    }

    public function getBalp(): ?string
    {
        return $this->balp;
    }

    public function setBalp(string $Balp): self
    {
        $this->balp = $Balp;

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

    public function getSupprimeLe(): ?\DateTimeInterface
    {
        return $this->supprimeLe;
    }

    public function setSupprimeLe(?\DateTimeInterface $supprimeLe): self
    {
        $this->supprimeLe = $supprimeLe;

        return $this;
    }

    public static function balpValidation(): array
    {
        return [
            new Assert\Regex([
                'pattern' => '/^[0-9-a-z-A-Z]{0,}$/',
                'message' => 'balp.caracterespecial',
            ])
        ];
    }

    public function getNomCompletCourt(): string
    {
        return ChaineDeCaracteres::prenomNomAbrege($this->getPrenom(), $this->getNom());
    }

    public function getNomPrenomCompletLong(): string
    {
        return $this->getNom() . ' ' . $this->getPrenom();
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
            $composant->setPilote($this);
        }

        return $this;
    }

    public function removeComposant(Composant $composant): self
    {
        if ($this->composants->contains($composant)) {
            $this->composants->removeElement($composant);
            // set the owning side to null (unless already changed)
            if ($composant->getPilote() === $this) {
                $composant->setPilote(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Composant[]
     */
    public function getSuppleantComposants(): Collection
    {
        return $this->suppleantComposants;
    }

    public function addSuppleantComposant(Composant $supplEantComposant): self
    {
        if (!$this->suppleantComposants->contains($supplEantComposant)) {
            $this->suppleantComposants[] = $supplEantComposant;
            $supplEantComposant->setPiloteSupppleant($this);
        }

        return $this;
    }

    public function removeSuppleantComposant(Composant $supplEantComposant): self
    {
        if ($this->suppleantComposants->contains($supplEantComposant)) {
            $this->suppleantComposants->removeElement($supplEantComposant);
            // set the owning side to null (unless already changed)
            if ($supplEantComposant->getPiloteSupppleant() === $this) {
                $supplEantComposant->setPiloteSupppleant(null);
            }
        }

        return $this;
    }

    /**
     * Récupération d'un objet Address pour l'envoi de mail
     * @return Address
     */
    public function getAddressObj(): Address
    {
        return new Address($this->getBalp(), $this->getPrenom() . ' ' . $this->getNom());
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

    public function __toString()
    {
        return $this->getNomCompletCourt();
    }
}
