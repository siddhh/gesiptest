<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200717102031 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Motif d\' intervention';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE motif_intervention_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE motif_intervention (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE motif_intervention_id_seq CASCADE');
        $this->addSql('DROP TABLE motif_intervention');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Chef de Bureau Si-2A\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Maintenance applicative\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Maintenance technique\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Opération d\'\'exploitation\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Opération de travaux sur site\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Ouverture de droits\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Ouverture de flux\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Résolution d\'\'incident\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, supprime_le, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Non communiqué\', NOW(), NOW(), NOW())');
    }
}
