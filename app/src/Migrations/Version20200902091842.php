<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200902091842 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table Sollicitation et modification de la table Service.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE sollicitation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sollicitation (id INT NOT NULL, service_sollicite_id INT NOT NULL, sollicite_par_id INT NOT NULL, sollicite_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ACC6315886A6FA58 ON sollicitation (service_sollicite_id)');
        $this->addSql('CREATE INDEX IDX_ACC631587862BC2A ON sollicitation (sollicite_par_id)');
        $this->addSql('ALTER TABLE sollicitation ADD CONSTRAINT FK_ACC6315886A6FA58 FOREIGN KEY (service_sollicite_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sollicitation ADD CONSTRAINT FK_ACC631587862BC2A FOREIGN KEY (sollicite_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE service ADD date_validation_balf TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD date_derniere_sollicitation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE sollicitation_id_seq CASCADE');
        $this->addSql('DROP TABLE sollicitation');
        $this->addSql('ALTER TABLE service DROP date_validation_balf');
        $this->addSql('ALTER TABLE service DROP date_derniere_sollicitation');
    }
}
