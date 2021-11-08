<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210401085922 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout d\'une table permettant de sauvegarder les validations des exploitants des publications de la météo des composants';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE meteo_validation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE meteo_validation (id INT NOT NULL, exploitant_id INT NOT NULL, periode_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, periode_fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9B0C373C7B9512F ON meteo_validation (exploitant_id)');
        $this->addSql('ALTER TABLE meteo_validation ADD CONSTRAINT FK_9B0C373C7B9512F FOREIGN KEY (exploitant_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE meteo_validation_id_seq CASCADE');
        $this->addSql('DROP TABLE meteo_validation');
    }
}
