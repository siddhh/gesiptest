<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200803074234 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'création de la référence "Grid mep"';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE grid_mep_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE grid_mep (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER INDEX idx_ec8486c9b5de73d4 RENAME TO IDX_EC8486C95A8AD2BD');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE grid_mep_id_seq CASCADE');
        $this->addSql('DROP TABLE grid_mep');
        $this->addSql('ALTER INDEX idx_ec8486c95a8ad2bd RENAME TO idx_ec8486c9b5de73d4');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'Chantier prévu au PSI\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'FDR – Fait l\'\'objet d’une feuille de route\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'Impacts usagers\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'Indisponibilité\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'Infra transverse impactante\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'Intervention HNO\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'Nombreux ESI concernés\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO grid_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'grid_mep_id_seq\'), \'REX prévu / à prévoir\', NOW(), NOW())');
    }
}
