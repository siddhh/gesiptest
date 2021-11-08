<?php

namespace App\Tests\Entity\Fiabilisation;

use App\Entity\Composant;
use App\Entity\Service;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\Fiabilisation\DemandeReferentielFlux;

class DemandeReferentielFluxTest extends KernelTestCase
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
     * @return DemandeReferentielFlux
     */
    private function creationDemande(): DemandeReferentielFlux
    {
        // On crée nos composants de test
        $composantA = new Composant();
        $composantA->setLabel("Composant A");
        $composantB = new Composant();
        $composantB->setLabel("Composant B");

        // On crée notre service demandeur
        $serviceDemandeur = new Service();
        $serviceDemandeur->setLabel("DEMANDEUR");

        // On crée enfin notre demande, que l'on retourne ensuite
        $demande = new DemandeReferentielFlux();
        $demande->setServiceDemandeur($serviceDemandeur);
        $demande->setComposantSource($composantA);
        $demande->setComposantTarget($composantB);
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
        $demande->setType(DemandeReferentielFlux::AJOUT);

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
        $demande->setType(DemandeReferentielFlux::AJOUT);

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
        $demande->setType(DemandeReferentielFlux::AJOUT);

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
        $demande->setType(DemandeReferentielFlux::AJOUT);

        // On test avant application
        $this->assertEquals(0, $demande->getComposantTarget()->getComposantsImpactes()->count());
        $this->assertNotContains($demande->getComposantSource(), $demande->getComposantTarget()->getComposantsImpactes());

        // On applique
        $demande->appliquer();

        // On test après application
        $this->assertEquals(1, $demande->getComposantTarget()->getComposantsImpactes()->count());
        $this->assertContains($demande->getComposantSource(), $demande->getComposantTarget()->getComposantsImpactes());
    }

    /**
     * Test d'application d'une demande en retrait
     */
    public function testApplicationRetrait()
    {
        // On récupère notre demande que l'on configure pour le test
        $demande = $this->creationDemande();
        $demande->setType(DemandeReferentielFlux::RETRAIT);
        $demande->getComposantTarget()->addComposantsImpacte($demande->getComposantSource());

        // On test avant application
        $this->assertEquals(1, $demande->getComposantTarget()->getComposantsImpactes()->count());
        $this->assertContains($demande->getComposantSource(), $demande->getComposantTarget()->getComposantsImpactes());

        // On applique
        $demande->appliquer();

        // On test après application
        $this->assertEquals(0, $demande->getComposantTarget()->getComposantsImpactes()->count());
        $this->assertNotContains($demande->getComposantSource(), $demande->getComposantTarget()->getComposantsImpactes());
    }
}
