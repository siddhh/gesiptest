<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200717150906 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Profil';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE profil_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE profil (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE profil_id_seq CASCADE');
        $this->addSql('DROP TABLE profil');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO profil (id, label, ajoute_le, maj_le) VALUES (nextval(\'profil_id_seq\'), \'Administrateur\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO profil (id, label, ajoute_le, maj_le) VALUES (nextval(\'profil_id_seq\'), \'DME/Pilotage\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO profil (id, label, ajoute_le, maj_le) VALUES (nextval(\'profil_id_seq\'), \'Intervenant\', NOW(), NOW())');
    }
}
