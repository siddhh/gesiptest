<?php

namespace App\Workflow;

use App\Entity\Composant\Annuaire;
use App\Entity\DemandeIntervention;
use App\Entity\Demande\HistoriqueStatus;
use App\Entity\Pilote;
use App\Entity\References\ListeDiffusionSi2a;
use App\Entity\Service;
use App\Entity\Composant;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatInstruite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;

class DestinatairesCollection
{

    /**
     * Liste des options permettant de
     */
    const OPTION_ADMINS                         = 'admins';               // Ajoute les administrateurs Gesip
    const OPTION_DEMANDEUR                      = 'demandeur';            // Ajoute le service demandeur de la demande
    const OPTION_INTERVENANTS                   = 'intervenants';         // Ajoute les intervenants (annuaire de la demande)
    const OPTION_PILOTE_EQUIPE_OU_DME           = 'piloteequipe|dme';     // Ajoute le pilote et l'équipe du composant (si l'équipe n'existe pas tous les services DME Gesip)
    const OPTION_SERVICES_COMPOSANT             = 'servicescomposant';    // Ajoute les services contenus dans l'annuaire du composant associé à la demande
    const OPTION_SERVICES_IMPACTES              = 'servicesimpactes';     // Ajoute les services contenus dans les annuaires des composants impactés par la demande
    const OPTION_SERVICES_CONSULTES             = 'serviceconsultes';     // Ajoute les services consultes si il y a eu consultation
    const OPTION_SERVICES_CONSULTES_OU_IMPACTES = 'consultes|impactes';   // Ajoute les services consultes si il y a eu consultation, sinon les services impactes
    const OPTION_SI2A                           = 'si2a';                 // Ajoute la liste de diffusion SI2A (paramètrée dans les référenciels Gesip)
    const OPTION_SUPERVISION                    = 'supervision';          // Ajoute les adresses du pole de supervision (plateau PSN)

    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    /** @var DemandeIntervention $demandeIntervention */
    private $demandeIntervention;

    /** @var Address[] $destinataires */
    private $destinataires;

    /**
     * Constructeur / initialisation (en dehors du workflow l'entityManager n'est pas forcément initialisé, utilisez le troisième paramètre dans ce cas)
     * @param DemandeIntervention $demandeIntervention
     * @param array $options
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(DemandeIntervention $demandeIntervention, array $options = [], EntityManagerInterface $entityManager = null)
    {
        $this->demandeIntervention = $demandeIntervention;
        if (empty($entityManager)) {
            $this->entityManager = $demandeIntervention->getMachineEtat()->getEntityManager();
        } else {
            $this->entityManager = $entityManager;
        }
        $this->destinataires = [];
        foreach ($options as $option) {
            switch ($option) {
                case self::OPTION_ADMINS:
                    $this->ajouteServiceParRoles([Service::ROLE_ADMIN]);
                    break;
                case self::OPTION_DEMANDEUR:
                    $this->ajouteDemandeur();
                    break;
                case self::OPTION_INTERVENANTS:
                    $this->ajouteIntervenants();
                    break;
                case self::OPTION_PILOTE_EQUIPE_OU_DME:
                    $this->ajouteEquipeOuServicesDme();
                    break;
                case self::OPTION_SERVICES_COMPOSANT:
                    $this->ajouteAnnuairesComposant();
                    break;
                case self::OPTION_SERVICES_IMPACTES:
                    $this->ajouteAnnuairesComposantsImpactes();
                    break;
                case self::OPTION_SERVICES_CONSULTES:
                    $this->ajouteServicesConsultes();
                    break;
                case self::OPTION_SERVICES_CONSULTES_OU_IMPACTES:
                    $this->ajouteAnnuairesConsultesOuImpactes();
                    break;
                case self::OPTION_SI2A:
                    $this->ajouteSI2A();
                    break;
                case self::OPTION_SUPERVISION:
                    $this->ajouteSupervision();
                    break;
                default:
                    throw new \Exception('Option d\'ajout de destinataires inconnue.');
            }
        }
    }

    /**
     * Ajoute les services via leur role
     */
    public function ajouteServiceParRoles(array $roles)
    {
        $services = $this->entityManager->getRepository(Service::class)->getServicesParRoles($roles);
        $this->ajouteDestinataires($services);
    }

    /**
     * Ajoute les services "flagué" DME
     */
    public function ajouteServicesDme()
    {
        $services = $this->entityManager->getRepository(Service::class)->findBy([
            'supprimeLe'        => null,
            'estPilotageDme'    => 1
        ]);
        $this->ajouteDestinataires($services);
    }

    /**
     * Ajoute le service qui a fait la demande à la liste des destinataires
     */
    public function ajouteDemandeur(): void
    {
        $this->ajouteDestinataires([
            $this->demandeIntervention->getDemandePar()
        ]);
    }

    /**
     * Ajoute la liste de diffusion SI2A (paramètrée dans les références) à la liste des destinataires
     */
    public function ajouteSI2A(): void
    {
        $listeSI2A = $this->entityManager->getRepository(ListeDiffusionSi2a::class)->findBy([
            'supprimeLe' => null
        ]);
        $this->ajouteDestinataires($listeSI2A);
    }

    /**
     * Ajoute l'équipe du composant associé à la demande (ou tous les service DME si l'équipe n'est pas définie) à la liste des destinataires
     */
    public function ajouteEquipeOuServicesDme(): void
    {
        $composant = $this->demandeIntervention->getComposant();

        // Ajout pilote si défini
        if (($piloteComposant = $composant->getPilote()) !== null) {
            $this->ajouteDestinataires([$piloteComposant]);
        }
        if (($piloteSuppleantComposant = $composant->getPiloteSuppleant()) !== null) {
            $this->ajouteDestinataires([$piloteSuppleantComposant]);
        }
        // Ajoute l'équipe ou les service DME si non définie
        if (($equipeComposant = $composant->getEquipe()) !== null) {
            $this->ajouteDestinataires([$equipeComposant]);
        } else {
            $this->ajouteServicesDme();
        }
    }

    /**
     * Ajoute les services intervenants (service réalisant l'intervention)
     */
    public function ajouteIntervenants(): void
    {
        $this->ajouteDestinataires($this->demandeIntervention->getServices()->toArray());
        $this->ajouteDestinataires($this->demandeIntervention->getExploitantExterieurs()->toArray());
    }

    /**
     * Ajoute les services de l'annuaire du composant
     */
    public function ajouteAnnuairesComposant(): void
    {
        $this->ajouteDestinataires($this->demandeIntervention->getComposant()->getAnnuaire()->toArray());
    }

    /**
     * Ajoute les services contenus dans les annuaires des composants impactés par la demande (y compris le composant associé à la demande)
     */
    public function ajouteAnnuairesComposantsImpactes(): void
    {
        $this->ajouteAnnuairesComposant();
        $idsComposantsHebergement = [];
        foreach ($this->demandeIntervention->getImpacts() as $impact) {
            foreach ($impact->getComposants() as $composant) {
                $this->ajouteDestinataires($composant->getAnnuaire()->toArray());
                // Si le composant impacté est un site d'hébergement et n'est pas le composant objet de la demande, on conserve son id
                if (($composant->getEstSiteHebergement()) && ($composant->getId() != $this->demandeIntervention->getComposant()->getId())) {
                    $idsComposantsHebergement[] = $composant->getId();
                }
            }
        }

        // Pour les composants impactés qui sont site d'hébergement, on récupère les composants impactés par ces composants
        if (count($idsComposantsHebergement) != 0) {
            $composantsImpactes = $this->entityManager->getRepository(Composant::class)->createQueryBuilder('c')
                ->addSelect('c', 'ci')
                ->leftJoin('c.composantsImpactes', 'ci')
                ->where('c.id IN (:ids)')
                ->andWhere('c.archiveLe IS NULL')
                ->andWhere('ci.archiveLe IS NULL')
                ->setParameter(':ids', $idsComposantsHebergement)
                ->getQuery()
                ->getResult();

            foreach ($composantsImpactes as $composantsImpacte) {
                foreach ($composantsImpacte->getComposantsImpactes() as $comp) {
                    $this->ajouteDestinataires($comp->getAnnuaire()->toArray());
                }
            }
        }
    }

    /**
     * Ajoute les services ayant fait l'objet d'une consultation
     *  et retourne le nombre de services consultés si consultation préalable sinon null.
     * @return int|null
     */
    public function ajouteServicesConsultes(): ?int
    {
        $annuairesIds = [];
        $consultationsHistoriqueStatus = $this->entityManager->getRepository(HistoriqueStatus::class)
            ->getHistoriqueParEtat($this->demandeIntervention, [ EtatConsultationEnCours::class, EtatInstruite::class ]);

        foreach ($consultationsHistoriqueStatus as $consultation) {
            $donnees = $consultation->getDonnees();
            if (isset($donnees['annuaires']) && !empty($donnees['annuaires'])) {
                $annuairesIds = array_merge($annuairesIds, $donnees['annuaires']);
            }
        }

        $annuaireConsultation = $this->entityManager->getRepository(Annuaire::class)->findBy(['id' => $annuairesIds]);
        $this->ajouteDestinataires($annuaireConsultation, true);

        return count($annuaireConsultation) > 0 ? count($annuaireConsultation) : null;
    }

    /**
     * Ajoute les services ayant fait l'objet d'une consultation
     *  ou les services impactés si pas de consultation préalable
     */
    public function ajouteAnnuairesConsultesOuImpactes(): void
    {
        if (null === $this->ajouteServicesConsultes()) {
            $this->ajouteAnnuairesComposantsImpactes();
        }
    }

    /**
     * Ajoute la ou les adresses du pole de supervision (plateau PSN)
     */
    public function ajouteSupervision(): void
    {
        $this->ajouteDestinataires([
            new Address('esi.lyon.psn@dgfip.finances.gouv.fr', 'Plateau de supervision'),
        ]);
    }

    /**
     * Ajoute de nouveaux destinataires à la liste si leur email n'existe pas.
     * (Par défaut, nous n'ajoutons pas les destinataires si ceux-ci sont indiqués supprimées)
     *
     * @param array $destinataires
     * @param bool  $memeSupprime
     *
     * @throws \Exception
     */
    public function ajouteDestinataires(array $destinataires = [], bool $memeSupprime = false): void
    {
        $noWhereEmail = 'no-where@dgfip.finances.gouv.fr';
        foreach ($destinataires as $destinataire) {
            if ($destinataire instanceof Address || $memeSupprime || $destinataire->getSupprimeLe() === null) {
                if ($destinataire instanceof Service) {
                    if ($destinataire->getEmail() !== $noWhereEmail) {
                        $this->destinataires[$destinataire->getEmail()] = new Address($destinataire->getEmail(), $destinataire->getLabel());
                    }
                } elseif ($destinataire instanceof Pilote) {
                    if ($destinataire->getBalp() !== $noWhereEmail) {
                        $this->destinataires[$destinataire->getBalp()] = new Address($destinataire->getBalp(), $destinataire->getNomCompletCourt());
                    }
                } elseif ($destinataire instanceof Annuaire) {
                    if ($destinataire->getBalf() !== $noWhereEmail) {
                        $annuaireLabel = $destinataire->getService()->getLabel() . ' (' . $destinataire->getMission()->getLabel() . ')';
                        $this->destinataires[$destinataire->getBalf()] = new Address($destinataire->getBalf(), $annuaireLabel);
                    }
                } elseif ($destinataire instanceof Address) {
                    if ($destinataire->getAddress() !== $noWhereEmail) {
                        $this->destinataires[$destinataire->getAddress()] = $destinataire;
                    }
                } elseif ($destinataire instanceof ListeDiffusionSi2a) {
                    if ($destinataire->getBalp() !== $noWhereEmail) {
                        $this->destinataires[$destinataire->getBalp()] = new Address($destinataire->getBalp(), $destinataire->getLabel());
                    }
                } else {
                    throw new \Exception('Format de destinataire non pris en charge.');
                }
            }
        }
    }

    /**
     * Supprime un ou plusieurs destinataires potentiels de la liste des destinataires courante
     * @param array $excluDestinataires
     */
    public function excluDestinataires(array $excluDestinataires = []): void
    {
        foreach ($excluDestinataires as $excluDestinataire) {
            $mailAddress = null;
            if ($excluDestinataire instanceof Service) {
                $mailAddress = $excluDestinataire->getEmail();
            } elseif ($excluDestinataire instanceof Pilote || $excluDestinataire instanceof Annuaire || $excluDestinataire instanceof ListeDiffusionSi2a) {
                $mailAddress = $excluDestinataire->getBalp();
            } elseif ($excluDestinataire instanceof Address) {
                $mailAddress = $excluDestinataire->getAddress();
            } else {
                throw new \Exception('Format de destinataire non pris en charge.');
            }
            if (!empty($this->destinataires[$mailAddress])) {
                unset($this->destinataires[$mailAddress]);
            }
        }
    }

    /**
     * Retourne la collection des destinataires
     */
    public function getDestinataires(): array
    {
        return $this->destinataires;
    }
}
