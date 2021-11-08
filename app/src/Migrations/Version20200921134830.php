<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200921134830 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création des tables nécessaire à la gestion des demandes d\'intervention.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE demande_historique_status_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE demande_impact_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE demande_intervention_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE demande_historique_status (id INT NOT NULL, demande_id INT NOT NULL, status VARCHAR(255) NOT NULL, donnees JSON DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7DED725D80E95E18 ON demande_historique_status (demande_id)');
        $this->addSql('CREATE TABLE demande_impact (id INT NOT NULL, nature_id INT NOT NULL, demande_id INT NOT NULL, numero_ordre INT NOT NULL, certitude BOOLEAN NOT NULL, commentaire TEXT DEFAULT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin_mini TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin_max TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_864D86E83BCB2E4B ON demande_impact (nature_id)');
        $this->addSql('CREATE INDEX IDX_864D86E880E95E18 ON demande_impact (demande_id)');
        $this->addSql('CREATE TABLE demande_impact_composant (impact_id INT NOT NULL, composant_id INT NOT NULL, PRIMARY KEY(impact_id, composant_id))');
        $this->addSql('CREATE INDEX IDX_C93C1B60D128BC9B ON demande_impact_composant (impact_id)');
        $this->addSql('CREATE INDEX IDX_C93C1B607F3310E7 ON demande_impact_composant (composant_id)');
        $this->addSql('CREATE TABLE demande_intervention (id INT NOT NULL, demande_par_id INT NOT NULL, composant_id INT NOT NULL, motif_intervention_id INT NOT NULL, numero VARCHAR(255) NOT NULL, demande_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, nature_intervention VARCHAR(255) NOT NULL, palier_applicatif BOOLEAN NOT NULL, description TEXT NOT NULL, solution_contournement TEXT DEFAULT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin_mini TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin_max TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, duree_retour_arriere INT NOT NULL, status VARCHAR(255) NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_86D186D14C0C045 ON demande_intervention (demande_par_id)');
        $this->addSql('CREATE INDEX IDX_86D186D17F3310E7 ON demande_intervention (composant_id)');
        $this->addSql('CREATE INDEX IDX_86D186D19C16AB78 ON demande_intervention (motif_intervention_id)');
        $this->addSql('CREATE TABLE demande_intervention_annuaire (demande_intervention_id INT NOT NULL, annuaire_id INT NOT NULL, PRIMARY KEY(demande_intervention_id, annuaire_id))');
        $this->addSql('CREATE INDEX IDX_38DEC4787607473E ON demande_intervention_annuaire (demande_intervention_id)');
        $this->addSql('CREATE INDEX IDX_38DEC4785132B86A ON demande_intervention_annuaire (annuaire_id)');
        $this->addSql('ALTER TABLE demande_historique_status ADD CONSTRAINT FK_7DED725D80E95E18 FOREIGN KEY (demande_id) REFERENCES demande_intervention (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impact ADD CONSTRAINT FK_864D86E83BCB2E4B FOREIGN KEY (nature_id) REFERENCES nature_impact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impact ADD CONSTRAINT FK_864D86E880E95E18 FOREIGN KEY (demande_id) REFERENCES demande_intervention (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impact_composant ADD CONSTRAINT FK_C93C1B60D128BC9B FOREIGN KEY (impact_id) REFERENCES demande_impact (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impact_composant ADD CONSTRAINT FK_C93C1B607F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_intervention ADD CONSTRAINT FK_86D186D14C0C045 FOREIGN KEY (demande_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_intervention ADD CONSTRAINT FK_86D186D17F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_intervention ADD CONSTRAINT FK_86D186D19C16AB78 FOREIGN KEY (motif_intervention_id) REFERENCES motif_intervention (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_intervention_annuaire ADD CONSTRAINT FK_38DEC4787607473E FOREIGN KEY (demande_intervention_id) REFERENCES demande_intervention (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_intervention_annuaire ADD CONSTRAINT FK_38DEC4785132B86A FOREIGN KEY (annuaire_id) REFERENCES annuaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact_composant DROP CONSTRAINT FK_C93C1B60D128BC9B');
        $this->addSql('ALTER TABLE demande_historique_status DROP CONSTRAINT FK_7DED725D80E95E18');
        $this->addSql('ALTER TABLE demande_impact DROP CONSTRAINT FK_864D86E880E95E18');
        $this->addSql('ALTER TABLE demande_intervention_annuaire DROP CONSTRAINT FK_38DEC4787607473E');
        $this->addSql('DROP SEQUENCE demande_historique_status_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE demande_impact_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE demande_intervention_id_seq CASCADE');
        $this->addSql('DROP TABLE demande_historique_status');
        $this->addSql('DROP TABLE demande_impact');
        $this->addSql('DROP TABLE demande_impact_composant');
        $this->addSql('DROP TABLE demande_intervention');
        $this->addSql('DROP TABLE demande_intervention_annuaire');
    }
}
