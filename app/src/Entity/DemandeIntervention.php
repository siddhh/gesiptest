<?php

namespace App\Entity;

use App\Entity\Composant\Annuaire;
use App\Entity\Demande\HistoriqueStatus;
use App\Entity\Demande\Impact;
use App\Entity\Demande\ImpactReel;
use App\Entity\Demande\SaisieRealise;
use App\Entity\Interfaces\HistorisableInterface;
use App\Entity\References\MotifIntervention;
use App\Entity\Traits\HorodatageTrait;
use App\Repository\DemandeInterventionRepository;
use App\Workflow\Etat;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatAnnulee;
use App\Workflow\Etats\EtatDebut;
use App\Workflow\Etats\EtatRefusee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatRenvoyee;
use App\Workflow\MachineEtat;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=DemandeInterventionRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class DemandeIntervention implements HistorisableInterface
{
    use HorodatageTrait;

    const NATURE_URGENT = 'urgent';
    const NATURE_NORMAL = 'normal';

    /**
     * Utilisé pour stockée la dernière machine à état retournée
     * @var $machineEtat MachineEtat
     */
    private $machineEtat;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity=Service::class, inversedBy="demandesIntervention")
     * @ORM\JoinColumn(nullable=false)
     */
    private $demandePar;

    /**
     * @ORM\Column(type="datetime")
     */
    private $demandeLe;

    /**
     * @ORM\ManyToOne(targetEntity=Composant::class, inversedBy="demandesIntervention")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull
     */
    private $composant;

    /**
     * @ORM\ManyToOne(targetEntity=MotifIntervention::class)
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotNull
     */
    private $motifIntervention;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     */
    private $natureIntervention;

    /**
     * @ORM\Column(type="boolean")
     * @Assert\NotNull
     */
    private $palierApplicatif;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank
     */
    private $description;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $solutionContournement;

    /**
     * @ORM\ManyToMany(targetEntity=Annuaire::class)
     */
    private $services;

    /**
     * @ORM\ManyToMany(targetEntity=Service::class)
     * @Assert\Count(
     *  max=3
     * )
     */
    private $exploitantExterieurs;

    /**
     * @ORM\Column(type="datetime")
     * @Assert\NotNull
     */
    private $dateDebut;

    /**
     * @ORM\Column(type="datetime")
     * @Assert\GreaterThanOrEqual(
     *  propertyPath="dateDebut"
     * )
     */
    private $dateFinMini;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $supprimeLe;

    /**
     * @ORM\Column(type="datetime")
     * @Assert\GreaterThanOrEqual(
     *  propertyPath="dateFinMini"
     * )
     */
    private $dateFinMax;

    /**
     * @ORM\Column(type="integer")
     * @Assert\PositiveOrZero
     */
    private $dureeRetourArriere;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity=HistoriqueStatus::class, mappedBy="demande", cascade={"persist"})
     * @ORM\OrderBy({"ajouteLe" = "DESC", "id" = "DESC"})
     */
    private $historiqueStatus;

    /**
     * @ORM\OneToMany(targetEntity=Impact::class, mappedBy="demande")
     * @ORM\OrderBy({"numeroOrdre" = "ASC"})
     * @Assert\Count(
     *  min=1
     * )
     */
    private $impacts;

    /**
     * @ORM\OneToMany(targetEntity=SaisieRealise::class, mappedBy="demande")
     * @ORM\OrderBy({"ajouteLe" = "ASC"})
     */
    private $saisieRealises;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $statusDonnees = [];

    /**
     * @ORM\ManyToMany(targetEntity=MepSsi::class, mappedBy="demandesInterventions")
     */
    private $mepSsis;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->exploitantExterieurs = new ArrayCollection();
        $this->historiqueStatus = new ArrayCollection();
        $this->impacts = new ArrayCollection();
        $this->status = EtatDebut::class;
        $this->impactReels = new ArrayCollection();
        $this->saisieRealises = new ArrayCollection();
        $this->mepSsis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;

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

    public function getDemandeLe(): ?\DateTimeInterface
    {
        return $this->demandeLe;
    }

    public function setDemandeLe(\DateTimeInterface $demandeLe): self
    {
        $this->demandeLe = $demandeLe;

        return $this;
    }

    public function getComposant(): ?Composant
    {
        return $this->composant;
    }

    public function setComposant(?Composant $composant): self
    {
        $this->composant = $composant;

        return $this;
    }

    public function getMotifIntervention(): ?MotifIntervention
    {
        return $this->motifIntervention;
    }

    public function setMotifIntervention(?MotifIntervention $motifIntervention): self
    {
        $this->motifIntervention = $motifIntervention;

        return $this;
    }

    public function getNatureIntervention(): ?string
    {
        return $this->natureIntervention;
    }

    public function setNatureIntervention(string $natureIntervention): self
    {
        $this->natureIntervention = $natureIntervention;

        return $this;
    }

    public function getPalierApplicatif(): ?bool
    {
        return $this->palierApplicatif;
    }

    public function setPalierApplicatif(bool $palierApplicatif): self
    {
        $this->palierApplicatif = $palierApplicatif;

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

    public function getSolutionContournement(): ?string
    {
        return $this->solutionContournement;
    }

    public function setSolutionContournement(?string $solutionContournement): self
    {
        $this->solutionContournement = $solutionContournement;

        return $this;
    }

    /**
     * @return Collection|Annuaire[]
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Annuaire $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services[] = $service;
        }

        return $this;
    }

    public function removeService(Annuaire $service): self
    {
        if ($this->services->contains($service)) {
            $this->services->removeElement($service);
        }

        return $this;
    }

    /**
     * @return Collection|Service[]
     */
    public function getExploitantExterieurs(): Collection
    {
        return $this->exploitantExterieurs;
    }

    public function addExploitantExterieur(Service $service): self
    {
        if (!$this->exploitantExterieurs->contains($service)) {
            $this->exploitantExterieurs[] = $service;
        }

        return $this;
    }

    public function removeExploitantExterieur(Service $service): self
    {
        if ($this->exploitantExterieurs->contains($service)) {
            $this->exploitantExterieurs->removeElement($service);
        }

        return $this;
    }

    /**
     * Retourne la liste completes des services exploitants (merge des annuaires et des exploitants exterieurs)
     * @return Service[]
     */
    public function getServiceExploitantsArray(): array
    {
        $serviceExploitants = [];
        foreach ($this->getServices() as $annuaire) {
            $service = $annuaire->getService();
            $serviceExploitants[$service->getId()] = $service;
        }
        foreach ($this->getExploitantExterieurs() as $service) {
            $serviceExploitants[$service->getId()] = $service;
        }
        uasort($serviceExploitants, function ($a, $b) {
            return strcmp($a->getLabel(), $b->getLabel());
        });
        return $serviceExploitants;
    }

    /**
     * Retourne true si le service fourni intervient sur cette demande d'intervention
     * @param Service $service
     * @return bool
     */
    public function isServiceExploitant(Service $service): bool
    {
        return in_array(
            $service->getId(),
            array_keys($this->getServiceExploitantsArray())
        );
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFinMini(): ?\DateTimeInterface
    {
        return $this->dateFinMini;
    }

    public function setDateFinMini(\DateTimeInterface $dateFinMini): self
    {
        $this->dateFinMini = $dateFinMini;

        return $this;
    }

    public function getDateFinMax(): ?\DateTimeInterface
    {
        return $this->dateFinMax;
    }

    public function setDateFinMax(\DateTimeInterface $dateFinMax): self
    {
        $this->dateFinMax = $dateFinMax;

        return $this;
    }

    public function getDureeRetourArriere(): ?int
    {
        return $this->dureeRetourArriere;
    }

    public function setDureeRetourArriere(int $dureeRetourArriere): self
    {
        $this->dureeRetourArriere = $dureeRetourArriere;

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
     * @return Collection|HistoriqueStatus[]
     */
    public function getHistoriqueStatus(): Collection
    {
        return $this->historiqueStatus;
    }

    public function addHistoriqueStatus(HistoriqueStatus $historiqueStatus): self
    {
        if (!$this->historiqueStatus->contains($historiqueStatus)) {
            $this->historiqueStatus[] = $historiqueStatus;
            $historiqueStatus->setDemande($this);
        }

        return $this;
    }

    public function removeHistoriqueStatus(HistoriqueStatus $historiqueStatus): self
    {
        if ($this->historiqueStatus->contains($historiqueStatus)) {
            $this->historiqueStatus->removeElement($historiqueStatus);
            // set the owning side to null (unless already changed)
            if ($historiqueStatus->getDemande() === $this) {
                $historiqueStatus->setDemande(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getNumero();
    }

    /**
     * Fonction permettant de générer un nouveau numéro de demande ainsi qu'à mettre à jour la date de la demande
     * @return $this
     */
    public function genererNumero(): self
    {
        $dateCourante = new \DateTime();
        $this->demandeLe = clone($dateCourante);
        $dateCourante->setTimezone(new \DateTimeZone('Europe/Paris'));
        $this->numero = $dateCourante->format('YmdHis');
        return $this;
    }

    /**
     * Fonction permettant de récupérer le libelle bien formée du statut de la demande
     * @return string|null
     */
    public function getStatusLibelle(): ?string
    {
        $etatCourant = $this->getMachineEtat()->getEtatActuel();
        if ($etatCourant instanceof Etat) {
            return $etatCourant->getLibelle();
        }
        return $this->status;
    }

    /**
     * @return Collection|Impact[]
     */
    public function getImpacts(): Collection
    {
        return $this->impacts;
    }

    public function addImpact(Impact $impact): self
    {
        if (!$this->impacts->contains($impact)) {
            $this->impacts[] = $impact;
            $impact->setDemande($this);
        }

        return $this;
    }

    public function removeImpact(Impact $impact): self
    {
        if ($this->impacts->contains($impact)) {
            $this->impacts->removeElement($impact);
            // set the owning side to null (unless already changed)
            if ($impact->getDemande() === $this) {
                $impact->setDemande(null);
            }
        }

        return $this;
    }

    public function getStatusDonnees(): ?array
    {
        return $this->statusDonnees;
    }

    public function setStatusDonnees(?array $statusDonnees): self
    {
        $this->statusDonnees = $statusDonnees;

        return $this;
    }

    public function getMachineEtat(?Service $service = null): MachineEtat
    {
        if (is_null($this->machineEtat)) {
            $this->machineEtat = new MachineEtat($this, $service);
        }
        return $this->machineEtat;
    }

    /**
     * @return Collection|SaisieRealise[]
     */
    public function getSaisieRealises(): Collection
    {
        return $this->saisieRealises;
    }

    public function addSaisieRealise(SaisieRealise $saisieRealise): self
    {
        if (!$this->saisieRealises->contains($saisieRealise)) {
            $this->saisieRealises[] = $saisieRealise;
            $saisieRealise->setDemande($this);
        }

        return $this;
    }

    public function removeSaisieRealise(SaisieRealise $saisieRealise): self
    {
        if ($this->saisieRealises->contains($saisieRealise)) {
            $this->saisieRealises->removeElement($saisieRealise);
            // set the owning side to null (unless already changed)
            if ($saisieRealise->getDemande() === $this) {
                $saisieRealise->setDemande(null);
            }
        }

        return $this;
    }

    /**
     * Fonction permettant de renvoyer la durée prévisionnelle en minutes de la demande d'intervention.
     *
     * @return int
     */
    public function getDureePrevisionnelleMinutes(): int
    {
        return abs(round(($this->getDateFinMax()->format('U') - $this->getDateDebut()->format('U')) / 60));
    }

    /**
     * Fonction permettant de renvoyer la durée réelle en minutes de la demande d'intervention.
     * (à besoin des SaisiesRealises & ImpactsReels)
     *
     * @return int
     */
    public function getDureeReelleMinutes(): int
    {
        // On défini nos dates de départ
        $dateDebut = 0;
        $dateFin = 0;

        // On boucle sur tous les réalisés et tous les impacts réels
        /** @var SaisieRealise $saisieRealise */
        foreach ($this->getSaisieRealises() as $saisieRealise) {
            /** @var ImpactReel $impactReel */
            foreach ($saisieRealise->getImpactReels() as $impactReel) {
                // Si nous avons une date de début et une date de fin réelle
                if ($impactReel->getDateDebut() && $impactReel->getDateFin()) {
                    // On transforme nos dates en Timestamp
                    $dd = $impactReel->getDateDebut()->format('U');
                    $df = $impactReel->getDateFin()->format('U');

                    // On compare nos dates de début et de fin pour prendre les écarts les plus grands entre toutes les saisies
                    if ($dateDebut === 0 || $dd < $dateDebut) {
                        $dateDebut = $dd;
                    }
                    if ($dateFin === 0 || $df > $dateFin) {
                        $dateFin = $df;
                    }
                }
            }
        }

        // On récupère la différence en seconde que l'on divise par 60 pour avoir les minutes, que l'on passe ensuite
        //  dans une fonction permettant de réaliser un arrondi.
        return abs(round(($dateFin - $dateDebut) / 60));
    }

    /**
     * Fonction permettant de renvoyer la durée de réponse du DME en jours.
     * (à besoin de l'historique des statuts)
     *
     * @return int
     * @throws \Exception
     */
    public function getDureeReponseDmeJours(): int
    {
        // Les status de réponse DME
        $statusReponses = [
            EtatAccordee::class,
            EtatRefusee::class
        ];

        // On défini nos dates de départ
        $dateDebut = $this->getAjouteLe()->format('U');
        $dateReponse = 0;

        // On parcourt notre historiques
        /** @var HistoriqueStatus $historiqueStatus */
        foreach ($this->getHistoriqueStatus() as $historiqueStatus) {
            if (in_array($historiqueStatus->getStatus(), $statusReponses)) {
                $dateReponse = $historiqueStatus->getAjouteLe()->format('U');
                break;
            }
        }

        // On calcul le temps de différence si une date de réponse à été trouvée
        if ($dateReponse === 0) {
            return 0;
        }
        return abs(round(($dateReponse - $dateDebut) / 86400));
    }

    /**
     * Fonction permettant de récupérer la date de l'accord.
     *
     * @return \DateTimeInterface|null
     */
    public function getDateAccord() : ?\DateTimeInterface
    {
        /** @var HistoriqueStatus $historiqueStatus */
        foreach ($this->getHistoriqueStatus() as $historiqueStatus) {
            if ($historiqueStatus->getStatus() === EtatAccordee::class) {
                return $historiqueStatus->getAjouteLe();
            }
        }

        return null;
    }

    /**
     * Fonction permettant de récupérer la première date de saisie du réalisé.
     *
     * @return \DateTimeInterface|null
     */
    public function getPremiereDateSaisieRealise() : ?\DateTimeInterface
    {
        /** @var HistoriqueStatus $historiqueStatus */
        $date = new \DateTime('now');
        $trouve = false;
        foreach ($this->getHistoriqueStatus() as $historiqueStatus) {
            if (($historiqueStatus->getStatus() === EtatInterventionReussie::class)
            || ($historiqueStatus->getStatus() === EtatInterventionEchouee::class)) {
                $trouve = true;
                if ($historiqueStatus->getAjouteLe() < $date) {
                    $date = $historiqueStatus->getAjouteLe();
                }
            }
        }

        return ($trouve == true ? $date : null);
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
            $mepSsi->addDemandesIntervention($this);
        }

        return $this;
    }

    public function removeMepSsi(MepSsi $mepSsi): self
    {
        if ($this->mepSsis->contains($mepSsi)) {
            $this->mepSsis->removeElement($mepSsi);
            $mepSsi->removeDemandesIntervention($this);
        }

        return $this;
    }

   /**
     * Fonction permettant de retourner la date limite de prise de décision Dme.
     * Règle du calcul de la date limite :
     *   - Une date limite est calculée uniquement si la demande n'est pas Accordée / Refusée / Annulée / Renvoyée
     * avant d'être en cours d'analyse.
     *   - À partir de la dernière date d'envoi de la demande (après renvoi !)
     *   - par défaut la date limite est la date de début d'intervention - 4J
     *   - si l'écart entre la date d'envoi et la date de début d'intervention est inférieur à 4J, la date limite  est fixée à date d'envoi + 1J (ou la date de début d'intervention si ces dernieres sont égales)
     * @return \DateTimeInterface|null
     */
    public function getDateLimiteDecisionDme() : ?\DateTimeInterface
    {
        // On initialise et récupère l'historique des statuts de la demande
        /** @var \DateTime $dateLimite */
        $dateEnvoi = null;
        $historiques = $this->getHistoriqueStatus();
        $dtz = new \DateTimeZone('Europe/Paris');

        // On parcourt l'historique des statuts
        foreach ($historiques as $historique) {
            // Si la demande est accordée, refusée, annulée ou renvoyée avant d'être en "Analyse en cours",
            //   alors pas de calcul de date !
            if (in_array($historique->getStatus(), [ EtatAccordee::class, EtatRefusee::class, EtatAnnulee::class, EtatRenvoyee::class ])) {
                $dateEnvoi = null;
                break;
            }

            // Si c'est le dernier état Analyse en cours, on récupère la date pour le calcul.
            if ($historique->getStatus() === EtatAnalyseEnCours::class) {
                $dateEnvoi = (clone $historique->getAjouteLe())->setTimeZone($dtz)->setTime(0, 0, 0);
                break;
            }
        }

        // Si nous n'avons pas de date d'envoi sur lequel se baser pour le calcul, on renvoie direct null
        if ($dateEnvoi === null) {
            return null;
        }

        // Par défaut, on positionne la date de réponse attendue à 4J avant le début d'intervention
        $dateLimite = (clone $this->getDateDebut())->setTimeZone($dtz)->setTime(0, 0, 0)->sub(new \DateInterval('P4D'));

        // Si on a réussi à obtenir une date d'envoi (permet de gérer les demandes sans historique générées via les Fixtures)
        if ($dateEnvoi !== null) {
            if ($dateEnvoi->format('Y-m-d') === (clone $this->getDateDebut())->setTimeZone($dtz)->format('Y-m-d')) {
                // Si la date d'envoi est la meme que la date d'intervention, alors la date de réponse attendue sera la meme que la date d'intervention
                $dateLimite = $dateEnvoi;
            } else {
                // Si on a moins de 4J pour prendre une décision avant le début de l'intervention, on fixe la limite à date envoi + 1J
                $dateMaxEnvoi = (clone $this->getDateDebut())->setTimeZone($dtz)->setTime(0, 0, 0)->sub(new \DateInterval('P4D'));
                if ($dateEnvoi >= $dateMaxEnvoi) {
                    $dateLimite = $dateEnvoi->add(new \DateInterval('P1D'));
                }
            }
        }

        // On retourne le résultat!
        return $dateLimite;
    }

    /**
     * Renvoi true, si la décision DME de la demande est en retard, sans prise en charge des heures, minutes, secondes
     * et soit DateLimiteRetard + 1 jour.
     *
     * @param \DateTimeInterface|null $dateReference
     *
     * @return bool
     */
    public function decisionDmeEnRetard(?\DateTimeInterface $dateReference): bool
    {
        // Si la date n'est pas passée en paramètre, alors on se base sur la date actuelle
        if ($dateReference === null) {
            $dateReference = new \DateTime('now');
        }
        $dateReference = (clone $dateReference)->setTimeZone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0, 0);
        // On récupère la date limite où l'on considère qu'une décision DME est en retard
        $dateLimite = $this->getDateLimiteDecisionDme();

        // On calcule si oui ou non nous sommes en retard
        return
            $dateLimite !== null &&
            $dateReference > $dateLimite;
    }
}
