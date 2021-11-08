<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200719190058 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Liste de diffusion SI2A';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE liste_diffusion_si2a_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE liste_diffusion_si2a (id INT NOT NULL, label VARCHAR(255) NOT NULL, fonction VARCHAR(255) NOT NULL, balp VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE liste_diffusion_si2a_id_seq CASCADE');
        $this->addSql('DROP TABLE liste_diffusion_si2a');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Chef de Bureau Si-2A\', \'Christine GRAVOSQUI\', \'christine.gravosqui@dgfip.finances.gouv.fr\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Adjoint Chef de Bureau Si-2A\', \'Jean-François GUILBERT\', \'jean-francois.guilbert@dgfip.finances.gouv.fr\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Responsable DME\', \'Laurent FRAISSE\', \'laurent-l.fraisse@dgfip.finances.gouv.fr\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Adjoint DME\', \'Jean-Cristophe POMMIER\', \'jean-christophe.pommier@dgfip.finances.gouv.fr\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Responsable Equipe CS1\', \'Claude GATTI\', \'claude.gatti@dgfip.finances.gouv.fr\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Responsable Equipe CS2\', \'Eric REBOUILLET-PETIOT\', \'eric.rebouillet-petiot@dgfip.finances.gouv.fr\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO liste_diffusion_si2a (id, fonction, label, balp, ajoute_le, maj_le) VALUES (
        nextval(\'liste_diffusion_si2a_id_seq\'), \'Responsable Equipe SOAE\', \'Marie-Pierre LIGOUT\', \'marie-pierre.ligout@dgfip.finances.gouv.fr\', NOW(), NOW())');
    }
}
