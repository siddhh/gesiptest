<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200706105220 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table pilote';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE pilote_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE pilote (id INT NOT NULL, equipe_id INT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, balp VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6A3254DD6D861B89 ON pilote (equipe_id)');
        $this->addSql('ALTER TABLE pilote ADD CONSTRAINT FK_6A3254DD6D861B89 FOREIGN KEY (equipe_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE pilote_id_seq CASCADE');
        $this->addSql('DROP TABLE pilote');
    }
}
