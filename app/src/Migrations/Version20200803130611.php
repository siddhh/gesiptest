<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200803130611 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table Demande Perimetre Applicatif.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE demande_perimetre_applicatif_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE demande_perimetre_applicatif (id INT NOT NULL, service_demandeur_id INT NOT NULL, composant_id INT NOT NULL, mission_id INT NOT NULL, type VARCHAR(10) NOT NULL, commentaire TEXT DEFAULT NULL, accepte_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, accepte_par_id INT DEFAULT NULL, refuse_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, refuse_par_id INT DEFAULT NULL, annule_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, annule_par_id INT DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9EA6B2E04A0A9BBC ON demande_perimetre_applicatif (service_demandeur_id)');
        $this->addSql('CREATE INDEX IDX_9EA6B2E07F3310E7 ON demande_perimetre_applicatif (composant_id)');
        $this->addSql('CREATE INDEX IDX_9EA6B2E0BE6CAE90 ON demande_perimetre_applicatif (mission_id)');
        $this->addSql('CREATE INDEX IDX_9EA6B2E0ECC92243 ON demande_perimetre_applicatif (accepte_par_id)');
        $this->addSql('CREATE INDEX IDX_9EA6B2E0BED2696A ON demande_perimetre_applicatif (refuse_par_id)');
        $this->addSql('CREATE INDEX IDX_9EA6B2E0F376B95 ON demande_perimetre_applicatif (annule_par_id)');
        $this->addSql('ALTER TABLE demande_perimetre_applicatif ADD CONSTRAINT FK_9EA6B2E04A0A9BBC FOREIGN KEY (service_demandeur_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_perimetre_applicatif ADD CONSTRAINT FK_9EA6B2E07F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_perimetre_applicatif ADD CONSTRAINT FK_9EA6B2E0BE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_perimetre_applicatif ADD CONSTRAINT FK_9EA6B2E0ECC92243 FOREIGN KEY (accepte_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_perimetre_applicatif ADD CONSTRAINT FK_9EA6B2E0BED2696A FOREIGN KEY (refuse_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_perimetre_applicatif ADD CONSTRAINT FK_9EA6B2E0F376B95 FOREIGN KEY (annule_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE demande_perimetre_applicatif_id_seq CASCADE');
        $this->addSql('DROP TABLE demande_perimetre_applicatif');
    }
}
