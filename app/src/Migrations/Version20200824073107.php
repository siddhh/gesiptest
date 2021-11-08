<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200824073107 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'création de la référence "Statut mep"';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE statut_mep_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE statut_mep (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE statut_mep_id_seq CASCADE');
        $this->addSql('DROP TABLE statut_mep');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO statut_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'statut_mep_id_seq\'), \'PROJET\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO statut_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'statut_mep_id_seq\'), \'CONFIRME\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO statut_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'statut_mep_id_seq\'), \'ARCHIVE\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO statut_mep (id, label, ajoute_le, maj_le) VALUES (nextval(\'statut_mep_id_seq\'), \'ERREUR\', NOW(), NOW())');
    }
}
