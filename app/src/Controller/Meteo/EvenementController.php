<?php

namespace App\Controller\Meteo;

use App\Entity\Demande\Impact;
use App\Entity\Service;
use App\Workflow\Etats\EtatInterventionEchouee;
use App\Workflow\Etats\EtatInterventionReussie;
use App\Workflow\Etats\EtatTerminee;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\DemandeIntervention;
use App\Entity\Meteo\Evenement;
use App\Entity\Demande\ImpactReel;
use App\Entity\References\ImpactMeteo;
use Symfony\Component\Security\Core\Security;

class EvenementController extends AbstractController
{
    /** @var Security $security */
    private $security;

    /**
     * Constructeur
     * @param Security $security
     */
    public function __construct(Security $security)
    {
        $this->security = $security;
    }


    /**
     * @Route("/meteo/transferts/evenements/{periodeDebut}/{demande}", name="meteo-transferts-evenements")
     */
    public function meteoTransfere(DemandeIntervention $demande, Request $request): Response
    {
        // On initialise quelques variables utiles
        $em = $this->getDoctrine()->getManager();
        $serviceActuel = $this->security->getUser();
        $tz = new \DateTimeZone('Europe/Paris');
        $referenceImpactsMeteo = new ArrayCollection($em->getRepository(ImpactMeteo::class)->findBy([ 'supprimeLe' => null ]));
        $listeComposantsTransferes = [];

        // Si nous sommes ROLE_GESTION et que nous avons un ?s= dans l'url, alors nous devons récupérer le service que nous voulons usurper.
        if ($this->isGranted(Service::ROLE_GESTION) && $request->query->get('s')) {
            $serviceActuel = $em->getRepository(Service::class)->find($request->query->get('s'));
            if ($serviceActuel === null) {
                throw new \Exception("Le service n'existe pas.");
            }
        }

        // On calcul nos dates de début et fin de période, en fonction de la date passé dans l'url
        $periodeDebut = \DateTime::createFromFormat('Ymd', $request->get('periodeDebut'))->setTimezone($tz)->setTime(0, 0, 0);
        // (si la date de début ne tombe pas un jeudi, alors nous reculons au jeudi précédent, histoire d'avoir des
        // semaines météos qui tombent toujours juste avec n'importe quelle date en entrée)
        if ($periodeDebut->format('N') != 4) {
            $periodeDebut->modify('last thursday');
        }
        $periodeFin = (clone $periodeDebut)->add(new \DateInterval('P6D'))->setTimezone($tz)->setTime(23, 59, 59);

        // On choisi le type d'impact à transférer en fonction de la demande
        // (Si la demande possède un statut Réussie / Échouée / Terminée, alors on va chercher les impacts réels)
        $impactType = Impact::class;
        if (in_array($demande->getStatus(), [
            EtatInterventionReussie::class,
            EtatInterventionEchouee::class,
            EtatTerminee::class
        ])) {
            $impactType = ImpactReel::class;
        }

        // On récupère les impacts de la demande, ainsi que ceux déjà effectuées sur cette semaine de météos
        $impacts = $em->getRepository($impactType)->impactsDemandePourTransferMeteo($demande, $periodeDebut, $periodeFin);
        $idsImpacts = array_column($impacts, 'id');
        $impactsDejaTransferees = $em->getRepository(Evenement::class)->impactsDejaTransfereesMeteo($impactType, $idsImpacts, $periodeDebut, $periodeFin);


        // On parcourt les impacts
        /** @var Impact|ImpactReel $impact */
        foreach ($impacts as $impact) {
            // et on récupère la liste des composants de l'impact courant
            $impactComposants = $impact->getComposants();

            // On parcourt chaque composant de l'impact
            foreach ($impactComposants as $impactComposant) {
                // On initialise quelques variables
                $evenement = null;

                // On essaie de déterminer si l'impact a déjà été transféré pour ce composant
                $dejaTransfere = $impactsDejaTransferees->filter(function (Evenement $evenement) use ($impactType, $impact, $impactComposant) {
                    if ($impactType === ImpactReel::class) {
                        return $evenement->getImpactReel() === $impact && $evenement->getComposant() === $impactComposant;
                    }
                        return $evenement->getImpactPrevisionnel() === $impact && $evenement->getComposant() === $impactComposant;
                })->count() > 0;

                // Si l'impact n'a pas déjà été transférée et que la météo du composant est active
                // et que le service courant est administrateur ou exploitant ou structure principale de l'exploitant du composant (si un exploitant est défini)
                if (!$dejaTransfere && $impactComposant->getMeteoActive() && (
                        ($impactComposant->getExploitant() && $impactComposant->getExploitant() === $serviceActuel) ||
                        ($impactComposant->getExploitant() && $impactComposant->getExploitant()->getStructurePrincipale() === $serviceActuel)
                    )
                ) {
                    // On récupère l'impact météo en fonction de la nature de l'impact saisie
                    $natureImpactMeteo = $referenceImpactsMeteo->filter(function (ImpactMeteo $im) use ($impact) {
                        return mb_strtolower($im->getLabel()) === mb_strtolower($impact->getNature()->getLabel());
                    });

                    // On crée notre évènement et on ajoute les informations
                    $evenement = new Evenement();
                    $evenement->setComposant($impactComposant);
                    $evenement->setSaisiePar($this->security->getUser());
                    $evenement->setCommentaire($impact->getCommentaire());
                    $evenement->setImpact($natureImpactMeteo->count() ? $natureImpactMeteo->first() : null);
                    $evenement->setDebut($impact->getDateDebut());

                    // Cas particulier du transfert d'un impact prévisionnel en évènement météo
                    if ($impactType === Impact::class) {
                        /** @var Impact $impact */
                        $demande = $impact->getDemande();
                        $evenement->setImpactPrevisionnel($impact);

                        if (count($impacts) == 1) {
                            $evenement->setDebut($demande->getDateDebut());
                            $evenement->setFin($impact->getDateFinMax());
                        } else {
                            $evenement->setFin($impact->getDateFinMax());
                        }


                    // Cas particulier du transfert d'un impact réel en évènement météo
                    } elseif ($impactType === ImpactReel::class) {
                        /** @var ImpactReel $impact */
                        $demande = $impact->getSaisieRealise()->getDemande();
                        $evenement->setImpactReel($impact);
                        $evenement->setFin($impact->getDateFin());
                    }

                    // On ajoute les informations par rapport à la demande associée
                    $evenement->setDescription($demande->getDescription());
                    $evenement->setTypeOperation($demande->getMotifIntervention());

                    // Contrôle si jamais le mapping de "la nature de l'intervention => impact météo" ne s'est pas bien passé
                    if ($evenement->getImpact() === null) {
                        throw new \Exception('Mapping impossible, le libellé de la nature de l\'impact #' . $impact->getId() . ' est inconnu.');
                    }

                    // Si nous avons bien une date de début et une date de fin pour l'évènement,
                    //  alors nous sommes de de bonnes dispositions pour sauvegarder l'évènement !
                    if ($evenement->getDebut() !== null && $evenement->getFin() !== null) {
                        // Si la date de début de l'événement est inférieure à la période météo
                        if ($evenement->getDebut() < $periodeDebut) {
                            // On cap le début au début de la période de météo
                            $evenement->setDebut((clone $periodeDebut)->setTimezone(new \DateTimeZone('UTC')));
                        }

                        // Si la date de début de l'événement est inférieure à la période météo
                        if ($evenement->getFin() > $periodeFin) {
                            // On cap la fin à la de la période de météo
                            $evenement->setFin((clone $periodeFin)->setTimezone(new \DateTimeZone('UTC')));
                        }

                        $listeComposantsTransferes[] = $impactComposant->getLabel();

                        // On persiste notre évènement !
                        $em->persist($evenement);
                    }
                }
            }
        }

        // On tire la chasse une bonne fois pour toute
        $em->flush();

        // On élimine les doublons de notre liste de composant pour lesquels nous avons eu des évènements a ajouter en base
        $listeComposantsTransferes = array_unique($listeComposantsTransferes);

        // On prépare notre message flash en fonction
        if (count($listeComposantsTransferes) > 0) {
            $this->addFlash(
                'success',
                "L'intervention que vous avez sélectionnée a bien été transférée
                dans la météo de cette semaine pour le(s) composant(s) : ".implode(", ", $listeComposantsTransferes)
            );
        } else {
            $this->addFlash(
                'success',
                "L'intervention que vous avez sélectionnée ne possède pas d'impact à tranférer."
            );
        }

        // On redirige vers la page spécifiée dans l'url
        return $this->redirect($request->get('url', '/'));
    }
}
