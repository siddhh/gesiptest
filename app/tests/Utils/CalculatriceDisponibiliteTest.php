<?php

namespace App\Tests\Utils;

use App\Entity\Composant\PlageUtilisateur;
use App\Tests\UserWebTestCase;
use App\Utils\CalculatriceDisponibilite;

class CalculatriceDisponibiliteTest extends UserWebTestCase
{
    /**
     * Permet d'initialiser et configurer notre calculatrice de disponibilité.s
     *
     * @return CalculatriceDisponibilite
     */
    private function initialisationCalculatrice(): CalculatriceDisponibilite
    {
        // On crée nos plages utilisateurs (Du lundi au jeudi de 10h à 15h et le vendredi de 00h à 00h)
        $plageUtilisateurs = [];
        for ($jour = PlageUtilisateur::LUNDI; $jour <= PlageUtilisateur::JEUDI; $jour++) {
            $plageUtilisateur = new PlageUtilisateur();
            $plageUtilisateur->setJour($jour);
            $plageUtilisateur->setDebut((new \DateTime())->setTime(10, 0, 0));
            $plageUtilisateur->setFin((new \DateTime())->setTime(15, 0, 0));
            $plageUtilisateurs[] = $plageUtilisateur;
        }
        $plageUtilisateur = new PlageUtilisateur();
        $plageUtilisateur->setJour(PlageUtilisateur::VENDREDI);
        $plageUtilisateur->setDebut((new \DateTime())->setTime(0, 0, 0));
        $plageUtilisateur->setFin((new \DateTime())->setTime(0, 0, 0));
        $plageUtilisateurs[] = $plageUtilisateur;

        // On crée notre environnement de test
        $calculatrice = new CalculatriceDisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 00:00:00'),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 23:59:59'),
            $plageUtilisateurs
        );

        // On retourne notre calculatrice de disponibilité configurée pour nos tests.
        return $calculatrice;
    }

    /**
     * Teste du planning après configuration de la calculatrice des disponibilités.
     */
    public function testPlanningDisponibilite()
    {
        // On crée notre calculatrice
        $calculatrice = $this->initialisationCalculatrice();

        // On teste que le planning de disponibilité semble correct
        $planning = $calculatrice->getPlanningsDisponibilite();
        $this->assertCount(7, $planning);
        $this->assertCount(1, $planning['2021-01-07']);
        $this->assertCount(1, $planning['2021-01-08']);
        $this->assertCount(0, $planning['2021-01-09']);
        $this->assertCount(0, $planning['2021-01-10']);
        $this->assertCount(1, $planning['2021-01-11']);
        $this->assertCount(1, $planning['2021-01-12']);
        $this->assertCount(1, $planning['2021-01-13']);
        $this->assertEquals(4*5*60 + 24*60, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $this->assertEquals(4*5*60 + 24*60, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $this->assertEquals(0, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $this->assertEquals(100, $calculatrice->getTauxDisponibilite());
        $this->assertEquals(0, $calculatrice->getTauxIndisponibilite());

        // On ajoute une indisponibilité en dehors de la période de disponiblité
        $calculatrice->ajoutIndisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-09 10:30:00', new \DateTimeZone('Europe/Paris')),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-10 10:30:00', new \DateTimeZone('Europe/Paris'))
        );

        // On vérifie les données
        $this->assertEquals(4*5*60 + 24*60, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $this->assertEquals(4*5*60 + 24*60, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $this->assertEquals(0, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $this->assertEquals(100, $calculatrice->getTauxDisponibilite());
        $this->assertEquals(0, $calculatrice->getTauxIndisponibilite());
    }

    /**
     * Teste du calendrier
     */
    public function testAjoutEnDehorsDeLaPeriodeDeDisponibilite()
    {
        // On crée notre calculatrice
        $calculatrice = $this->initialisationCalculatrice();

        // On ajoute une indisponibilité en dehors de la période de disponibilité
        $calculatrice->ajoutIndisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-09 10:30:00', new \DateTimeZone('Europe/Paris')),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-10 10:30:00', new \DateTimeZone('Europe/Paris'))
        );

        // On vérifie les données
        $this->assertEquals(4*5*60 + 24*60, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $this->assertEquals(4*5*60 + 24*60, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $this->assertEquals(0, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $this->assertEquals(100, $calculatrice->getTauxDisponibilite());
        $this->assertEquals(0, $calculatrice->getTauxIndisponibilite());
    }

    /**
     * Teste du calendrier
     */
    public function testAjoutSurPlusieursJours()
    {
        // On crée notre calculatrice
        $calculatrice = $this->initialisationCalculatrice();

        // On ajoute une indisponibilité en dehors de la période de disponibilité
        $calculatrice->ajoutIndisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-11 11:00:00', new \DateTimeZone('Europe/Paris')),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-13 11:00:00', new \DateTimeZone('Europe/Paris'))
        );

        // On vérifie les données
        $indispoTotal = 10*60;
        $this->assertEquals($indispoTotal, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $dispoTheorique = 4*5*60 + 24*60;
        $this->assertEquals($dispoTheorique, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $dispoReelle = $dispoTheorique - $indispoTotal;
        $this->assertEquals($dispoReelle, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $tauxDispo = round(($dispoReelle / $dispoTheorique) * 100, 2);
        $this->assertEquals($tauxDispo, $calculatrice->getTauxDisponibilite());
        $tauxIndispo = round(100 - $tauxDispo, 2);
        $this->assertEquals($tauxIndispo, $calculatrice->getTauxIndisponibilite());
    }

    /**
     * Teste de l'ajout d'une indisponibilité à cheval entre une journée sans disponibilité et une journée disponible
     */
    public function testAjoutAChevalAvant()
    {
        // On crée notre calculatrice
        $calculatrice = $this->initialisationCalculatrice();

        // On ajoute une indisponibilité en dehors de la période de disponibilité
        $calculatrice->ajoutIndisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-10 11:00:00', new \DateTimeZone('Europe/Paris')),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-11 11:00:00', new \DateTimeZone('Europe/Paris'))
        );

        // On vérifie les données
        $indispoTotal = 1*60;
        $this->assertEquals($indispoTotal, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $dispoTheorique = 4*5*60 + 24*60;
        $this->assertEquals($dispoTheorique, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $dispoReelle = $dispoTheorique - $indispoTotal;
        $this->assertEquals($dispoReelle, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $tauxDispo = round(($dispoReelle / $dispoTheorique) * 100, 2);
        $this->assertEquals($tauxDispo, $calculatrice->getTauxDisponibilite());
        $tauxIndispo = round(100 - $tauxDispo, 2);
        $this->assertEquals($tauxIndispo, $calculatrice->getTauxIndisponibilite());
    }

    /**
     * Teste de l'ajout d'une indisponibilité à cheval entre une journée disponible et une journée sans disponibilité
     */
    public function testAjoutAChevalApres()
    {
        // On crée notre calculatrice
        $calculatrice = $this->initialisationCalculatrice();

        // On ajoute une indisponibilité en dehors de la période de disponibilité
        $calculatrice->ajoutIndisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-08 13:00:00', new \DateTimeZone('Europe/Paris')),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-09 10:00:00', new \DateTimeZone('Europe/Paris'))
        );

        // On vérifie les données
        $indispoTotal = 11*60;
        $this->assertEquals($indispoTotal, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $dispoTheorique = 4*5*60 + 24*60;
        $this->assertEquals($dispoTheorique, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $dispoReelle = $dispoTheorique - $indispoTotal;
        $this->assertEquals($dispoReelle, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $tauxDispo = round(($dispoReelle / $dispoTheorique) * 100, 2);
        $this->assertEquals($tauxDispo, $calculatrice->getTauxDisponibilite());
        $tauxIndispo = round(100 - $tauxDispo, 2);
        $this->assertEquals($tauxIndispo, $calculatrice->getTauxIndisponibilite());
    }

    /**
     * Teste de l'ajout d'une indisponibilité au cours d'une journée
     */
    public function testAjoutPendant()
    {
        // On crée notre calculatrice
        $calculatrice = $this->initialisationCalculatrice();

        // On ajoute une indisponibilité en dehors de la période de disponibilité
        $calculatrice->ajoutIndisponibilite(
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 13:00:00', new \DateTimeZone('Europe/Paris')),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-07 14:00:00', new \DateTimeZone('Europe/Paris'))
        );

        // On vérifie les données
        $indispoTotal = 1*60;
        $this->assertEquals($indispoTotal, $calculatrice->getDureeIndisponibiliteRelleMinutes());
        $dispoTheorique = 4*5*60 + 24*60;
        $this->assertEquals($dispoTheorique, $calculatrice->getDureeDisponibiliteTheoriqueMinutes());
        $dispoReelle = $dispoTheorique - $indispoTotal;
        $this->assertEquals($dispoReelle, $calculatrice->getDureeDisponibiliteReelleMinutes());
        $tauxDispo = round(($dispoReelle / $dispoTheorique) * 100, 2);
        $this->assertEquals($tauxDispo, $calculatrice->getTauxDisponibilite());
        $tauxIndispo = round(100 - $tauxDispo, 2);
        $this->assertEquals($tauxIndispo, $calculatrice->getTauxIndisponibilite());
        $this->assertCount(2, $calculatrice->getPlanningsDisponibilite()['2021-01-07']);
    }
}
