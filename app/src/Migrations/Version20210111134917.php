<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210111134917 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table permetant de stocker des MEP SSI.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE mep_ssi_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE mep_ssi (id INT NOT NULL, equipe_id INT NOT NULL, grid_id INT DEFAULT NULL, statut_id INT NOT NULL, palier VARCHAR(255) NOT NULL, visibilite VARCHAR(255) NOT NULL, lep TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mep_debut TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mep_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mes TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, description TEXT DEFAULT NULL, impacts TEXT DEFAULT NULL, risques TEXT DEFAULT NULL, mots_clefs TEXT DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2343B6496D861B89 ON mep_ssi (equipe_id)');
        $this->addSql('CREATE INDEX IDX_2343B6492CF16895 ON mep_ssi (grid_id)');
        $this->addSql('CREATE INDEX IDX_2343B649F6203804 ON mep_ssi (statut_id)');
        $this->addSql('CREATE TABLE mep_ssi_composant (mep_ssi_id INT NOT NULL, composant_id INT NOT NULL, PRIMARY KEY(mep_ssi_id, composant_id))');
        $this->addSql('CREATE INDEX IDX_A18E54A644406A48 ON mep_ssi_composant (mep_ssi_id)');
        $this->addSql('CREATE INDEX IDX_A18E54A67F3310E7 ON mep_ssi_composant (composant_id)');
        $this->addSql('CREATE TABLE mep_ssi_pilote (mep_ssi_id INT NOT NULL, pilote_id INT NOT NULL, PRIMARY KEY(mep_ssi_id, pilote_id))');
        $this->addSql('CREATE INDEX IDX_23D551D544406A48 ON mep_ssi_pilote (mep_ssi_id)');
        $this->addSql('CREATE INDEX IDX_23D551D5F510AAE9 ON mep_ssi_pilote (pilote_id)');
        $this->addSql('CREATE TABLE mep_ssi_demande_intervention (mep_ssi_id INT NOT NULL, demande_intervention_id INT NOT NULL, PRIMARY KEY(mep_ssi_id, demande_intervention_id))');
        $this->addSql('CREATE INDEX IDX_39AFE6F944406A48 ON mep_ssi_demande_intervention (mep_ssi_id)');
        $this->addSql('CREATE INDEX IDX_39AFE6F97607473E ON mep_ssi_demande_intervention (demande_intervention_id)');
        $this->addSql('ALTER TABLE mep_ssi ADD CONSTRAINT FK_2343B6496D861B89 FOREIGN KEY (equipe_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi ADD CONSTRAINT FK_2343B6492CF16895 FOREIGN KEY (grid_id) REFERENCES grid_mep (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi ADD CONSTRAINT FK_2343B649F6203804 FOREIGN KEY (statut_id) REFERENCES statut_mep (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_composant ADD CONSTRAINT FK_A18E54A644406A48 FOREIGN KEY (mep_ssi_id) REFERENCES mep_ssi (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_composant ADD CONSTRAINT FK_A18E54A67F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_pilote ADD CONSTRAINT FK_23D551D544406A48 FOREIGN KEY (mep_ssi_id) REFERENCES mep_ssi (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_pilote ADD CONSTRAINT FK_23D551D5F510AAE9 FOREIGN KEY (pilote_id) REFERENCES pilote (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_demande_intervention ADD CONSTRAINT FK_39AFE6F944406A48 FOREIGN KEY (mep_ssi_id) REFERENCES mep_ssi (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_demande_intervention ADD CONSTRAINT FK_39AFE6F97607473E FOREIGN KEY (demande_intervention_id) REFERENCES demande_intervention (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mep_ssi_composant DROP CONSTRAINT FK_A18E54A644406A48');
        $this->addSql('ALTER TABLE mep_ssi_pilote DROP CONSTRAINT FK_23D551D544406A48');
        $this->addSql('ALTER TABLE mep_ssi_demande_intervention DROP CONSTRAINT FK_39AFE6F944406A48');
        $this->addSql('DROP SEQUENCE mep_ssi_id_seq CASCADE');
        $this->addSql('DROP TABLE mep_ssi');
        $this->addSql('DROP TABLE mep_ssi_composant');
        $this->addSql('DROP TABLE mep_ssi_pilote');
        $this->addSql('DROP TABLE mep_ssi_demande_intervention');
    }
}
