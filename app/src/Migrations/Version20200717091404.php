<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200717091404 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Motif de Refus';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE motif_refus_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE motif_refus (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE motif_refus_id_seq CASCADE');
        $this->addSql('DROP TABLE motif_refus');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO motif_refus (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_refus_id_seq\'), \'Changement d\'\'impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_refus (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_refus_id_seq\'), \'Replanification de la date\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_refus (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_refus_id_seq\'), \'Rédaction\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_refus (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_refus_id_seq\'), \'Saisie dans GESIP hors périmètre\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_refus (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_refus_id_seq\'), \'Autre\', NOW(), NOW())');
    }
}
