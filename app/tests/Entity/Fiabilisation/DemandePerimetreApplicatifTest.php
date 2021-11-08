<?php

namespace App\Tests\Entity\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Fiabilisation\DemandePerimetreApplicatif;
use App\Entity\Service;
use App\Entity\References\Mission;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DemandePerimetreApplicatifTest extends KernelTestCase
{
    /**
     * Fonction privée permettant de créer un service qui effectue l'action
     * @return Service
     */
    private function creationServiceAction(): Service
    {
        $serviceAction = new Service();
        $serviceAction->setLabel("ServiceAction");
        return $serviceAction;
    }

    /**
     * Fonction privée permettant de créer une demande
     * @return DemandePerimetreApplicatif
     */
    private function creationDemande(): DemandePerimetreApplicatif
    {
        // On crée un composant et une mission de test
        $composant = new Composant();
        $composant->setLabel("Composant");
        $mission = new Mission();
        $mission->setLabel("Mission");

        // On crée notre service demandeur
        $serviceDemandeur = new Service();
        $serviceDemandeur->setLabel("DEMANDEUR");

        // On crée enfin notre demande, que l'on retourne ensuite
        $demande = new DemandePerimetreApplicatif();
        $demande->setServiceDemandeur($serviceDemandeur);
        $demande->setComposant($composant);
        $demande->setMission($mission);
        return $demande;
    }

    /**
     * Test d'acceptation d'une demande
     */
    public function testAcceptation()
    {
        // On récupère notre service faisant l'action
        $serviceAction = $this->creationServiceAction();

        // On récupère notre demande que l'on configure pour le test
        $demande = $this->creationDemande();
        $demande->setType(DemandePerimetreApplicatif::AJOUT);

        // On test avant acceptation
        $this->assertNull($demande->getAccepteLe());
        $this->assertNull($demande->getAcceptePar());
        $this->assertTrue($demande->estEnAttente());

        // On accepte
        $demande->accepter($serviceAction);

        // On test après acceptation
        $this->assertNotNull($demande->getAccepteLe());
        $this->assertEquals($serviceAction->getLabel(), $demande->getAcceptePar()->getLabel());
        $this->assertFalse($demande->estEnAttente());
    }

    /**
     * Test de refus d'une demande
     */
    public function testRefus()
    {
        // On récupère notre service faisant l'action
        $serviceAction = $this->creationServiceAction();

        // On récupère notre demande que l'on configure pour le test
        $demande = $this->creationDemande();
        $demande->setType(DemandePerimetreApplicatif::AJOUT);

        // On test avant refus
        $this->assertNull($demande->getRefuseLe());
        $this->assertNull($demande->getRefusePar());
        $this->assertTrue($demande->estEnAttente());

        // On refuse
        $demande->refuser($serviceAction);

        // On test après refus
        $this->assertNotNull($demande->getRefuseLe());
        $this->assertEquals($serviceAction->getLabel(), $demande->getRefusePar()->getLabel());
        $this->assertFalse($demande->estEnAttente());
    }

    /**
     * Test d'annulation d'une demande
     */
    public function testAnnulation()
    {
        // On récupère notre service faisant l'action
        $serviceAction = $this->creationServiceAction();

        // On récupère notre demande que l'on configure pour le test
        $demande = $this->creationDemande();
        $demande->setType(DemandePerimetreApplicatif::AJOUT);

        // On test avant annulation
        $this->assertNull($demande->getAnnuleLe());
        $this->assertNull($demande->getAnnulePar());
        $this->assertTrue($demande->estEnAttente());

        // On annule
        $demande->annuler($serviceAction);

        // On test après annulation
        $this->assertNotNull($demande->getAnnuleLe());
        $this->assertEquals($serviceAction->getLabel(), $demande->getAnnulePar()->getLabel());
        $this->assertFalse($demande->estEnAttente());
    }

    /**
     * Test d'application d'une demande en ajout
     */
    public function testApplicationAjout()
    {
        // On récupère notre demande que l'on configure pour le test
        $demande = $this->creationDemande();
        $demande->setType(DemandePerimetreApplicatif::AJOUT);

        // On test avant application
        $this->assertEquals(0, $demande->getComposant()->getAnnuaire()->count());

        // On applique
        $demande->appliquer();

        // On récupère l'annuaire du composant
        $annuaires = $demande->getComposant()->getAnnuaire();
        /** @var Composant\Annuaire $annuaire */
        $annuaire = $annuaires->first();

        // On test après application
        $this->assertEquals(1, $annuaires->count());
        $this->assertEquals($demande->getServiceDemandeur(), $annuaire->getService());
        $this->assertEquals($demande->getComposant(), $annuaire->getComposant());
        $this->assertEquals($demande->getMission(), $annuaire->getMission());
    }

    /**
     * Test d'application d'une demande en retrait
     */
    public function testApplicationRetrait()
    {
        // On récupère notre demande que l'on configure pour le test
        $demande = $this->creationDemande();
        $demande->setType(DemandePerimetreApplicatif::RETRAIT);

        // Annuaire 1 - Correspondant à la demande
        $annuaire = new Composant\Annuaire();
        $annuaire->setService($demande->getServiceDemandeur());
        $annuaire->setMission($demande->getMission());
        $demande->getComposant()->addAnnuaire($annuaire);

        // Annuaire 2 - Ne correspondant pas à la demande
        $annuaireALaisser = new Composant\Annuaire();
        $annuaireALaisser->setService($demande->getServiceDemandeur());
        $annuaireALaisser->setMission((new Mission())->setLabel("Autre mission"));
        $demande->getComposant()->addAnnuaire($annuaireALaisser);

        // On test avant application
        $this->assertEquals(2, $demande->getComposant()->getAnnuaire()->count());

        // On applique
        $demande->appliquer();

        // On test après application
        // En contrôlant si le dernier annuaire correspond avec celui que nous devions laisser
        $annuaires = $demande->getComposant()->getAnnuaire();
        $this->assertEquals(1, $annuaires->count());
        $this->assertEquals($annuaireALaisser, $annuaires->first());
    }
}
