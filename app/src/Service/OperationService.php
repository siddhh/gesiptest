<?php

namespace App\Service;

use App\Entity\Composant;
use App\Entity\DemandeIntervention;
use App\Entity\MepSsi;
use App\Entity\Pilote;
use App\Entity\References\NatureImpact;
use App\Entity\Service;
use App\Workflow\Etats\EtatAccordee;
use App\Workflow\Etats\EtatAnalyseEnCours;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionEnCours;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatSaisirRealise;
use App\Workflow\Etats\EtatInstruite;
use App\Workflow\Etats\EtatConsultationEnCours;
use App\Workflow\Etats\EtatConsultationEnCoursCdb;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class OperationService
{
    /** @var EntityManagerInterface */
    private $em;

    /**
     * OperationService constructor.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Permet de récupérer toutes les opérations (Demandes d'intervention ou Mep SSI) dans une période donnée.
     *
     * @param \DateTime $dateDebut
     * @param \DateTime $dateFin
     *
     * @return ArrayCollection
     * @throws \Exception
     */
    public function findAllSurPeriode(\DateTime $periodeDebut, \DateTime $periodeFin) : ArrayCollection
    {
        // On précharge quelques informations
        $this->em->getRepository(Service::class)->findAll();
        $this->em->getRepository(Composant::class)->findAll();
        $this->em->getRepository(Pilote::class)->findAll();
        $this->em->getRepository(NatureImpact::class)->findAll();

        // On se prépare à chercher en UTC (puisque la base de données stocke en UTC les opérations)
        $periodeDebut = (clone $periodeDebut)->setTimezone(new \DateTimeZone('UTC'));
        $periodeFin = (clone $periodeFin)->setTimezone(new \DateTimeZone('UTC'));

        // On récupère les demandes d'interventions
        $demandes = $this->em->createQueryBuilder()
            ->select(['di', 'c', 'dihisto', 'intervenants', 'i'])
            ->from(DemandeIntervention::class, 'di')
            ->leftJoin('di.impacts', 'i')
            ->join('di.composant', 'c')
            ->leftJoin('di.services', 'intervenants')
            ->leftJoin('di.historiqueStatus', 'dihisto')
            ->andWhere('di.status IN (:statuts)')
                ->setParameter('statuts', [
                    EtatAnalyseEnCours::class,
                    EtatAccordee::class,
                    EtatInterventionEnCours::class,
                    EtatSaisirRealise::class,
                    EtatInterventionReussie::class,
                    EtatInterventionEchouee::class,
                    EtatInstruite::class,
                    EtatConsultationEnCours::class,
                    EtatConsultationEnCoursCdb::class
                ])
            ->andWhere('i.dateDebut <= :periodeFin AND i.dateFinMax >= :periodeDebut')
                ->setParameter('periodeDebut', $periodeDebut)
                ->setParameter('periodeFin', $periodeFin)
            ->getQuery()->getResult();

        // On récupère les MEP SSI
        $meps = $this->em->createQueryBuilder()
            ->select(['mep', 'c', 's'])
            ->from(MepSsi::class, 'mep')
            ->join('mep.composants', 'c')
            ->join('mep.statut', 's')
            ->andWhere('mep.mepDebut <= :periodeFin AND mep.mepFin >= :periodeDebut OR mep.mes <= :periodeFin AND mep.mes >= :periodeDebut')
                ->setParameter('periodeDebut', $periodeDebut)
                ->setParameter('periodeFin', $periodeFin)
            ->getQuery()->getResult();

        // On fusionne les deux listes
        return new ArrayCollection(array_merge($meps, $demandes));
    }
}
