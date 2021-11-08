<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200721090201 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création des tables annuaire, plage utilisateur et composant';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE annuaire_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE composant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE plage_utilisateur_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE annuaire (id INT NOT NULL, mission_id INT NOT NULL, service_id INT NOT NULL, composant_id INT NOT NULL, balf VARCHAR(255) DEFAULT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_456BA70BBE6CAE90 ON annuaire (mission_id)');
        $this->addSql('CREATE INDEX IDX_456BA70BED5CA9E6 ON annuaire (service_id)');
        $this->addSql('CREATE INDEX IDX_456BA70B7F3310E7 ON annuaire (composant_id)');
        $this->addSql('CREATE TABLE composant (id INT NOT NULL, usager_id INT NOT NULL, domaine_id INT DEFAULT NULL, exploitant_id INT DEFAULT NULL, equipe_id INT DEFAULT NULL, pilote_id INT DEFAULT NULL, pilote_suppleant_id INT DEFAULT NULL, type_element_id INT NOT NULL, bureau_rattachement_id INT DEFAULT NULL, label VARCHAR(255) NOT NULL, code_carto VARCHAR(255) NOT NULL, intitule_plage_utilisateur VARCHAR(255) NOT NULL, duree_plage_utilisateur INT NOT NULL, meteo_active BOOLEAN NOT NULL, est_site_hebergement BOOLEAN DEFAULT NULL, archive_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EC8486C94F36F0FC ON composant (usager_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C94272FC9F ON composant (domaine_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C9C7B9512F ON composant (exploitant_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C96D861B89 ON composant (equipe_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C9F510AAE9 ON composant (pilote_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C9B5DE73D4 ON composant (pilote_suppleant_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C921CFC01 ON composant (type_element_id)');
        $this->addSql('CREATE INDEX IDX_EC8486C930487EBC ON composant (bureau_rattachement_id)');
        $this->addSql('CREATE TABLE composant_composant (composant_source INT NOT NULL, composant_target INT NOT NULL, PRIMARY KEY(composant_source, composant_target))');
        $this->addSql('CREATE INDEX IDX_238C3C39EF1A5DBF ON composant_composant (composant_source)');
        $this->addSql('CREATE INDEX IDX_238C3C39F6FF0D30 ON composant_composant (composant_target)');
        $this->addSql('CREATE TABLE plage_utilisateur (id INT NOT NULL, composant_id INT NOT NULL, jour SMALLINT NOT NULL, debut TIME(0) WITHOUT TIME ZONE NOT NULL, fin TIME(0) WITHOUT TIME ZONE NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A45A40C7F3310E7 ON plage_utilisateur (composant_id)');
        $this->addSql('ALTER TABLE annuaire ADD CONSTRAINT FK_456BA70BBE6CAE90 FOREIGN KEY (mission_id) REFERENCES mission (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE annuaire ADD CONSTRAINT FK_456BA70BED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE annuaire ADD CONSTRAINT FK_456BA70B7F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C94F36F0FC FOREIGN KEY (usager_id) REFERENCES usager (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C94272FC9F FOREIGN KEY (domaine_id) REFERENCES domaine (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C9C7B9512F FOREIGN KEY (exploitant_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C96D861B89 FOREIGN KEY (equipe_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C9F510AAE9 FOREIGN KEY (pilote_id) REFERENCES pilote (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C9B5DE73D4 FOREIGN KEY (pilote_suppleant_id) REFERENCES pilote (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C921CFC01 FOREIGN KEY (type_element_id) REFERENCES type_element (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant ADD CONSTRAINT FK_EC8486C930487EBC FOREIGN KEY (bureau_rattachement_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant_composant ADD CONSTRAINT FK_238C3C39EF1A5DBF FOREIGN KEY (composant_source) REFERENCES composant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE composant_composant ADD CONSTRAINT FK_238C3C39F6FF0D30 FOREIGN KEY (composant_target) REFERENCES composant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE plage_utilisateur ADD CONSTRAINT FK_A45A40C7F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE domaine ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE domaine ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE impact_meteo ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE impact_meteo ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE liste_diffusion_si2a ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE liste_diffusion_si2a ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE mission ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE mission ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE motif_intervention ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE motif_intervention ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE motif_refus ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE motif_refus ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE motif_renvoi ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE motif_renvoi ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE nature_impact ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE nature_impact ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE profil ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE profil ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE type_element ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE usager ALTER ajoute_le SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE usager ALTER maj_le SET DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE annuaire DROP CONSTRAINT FK_456BA70B7F3310E7');
        $this->addSql('ALTER TABLE composant_composant DROP CONSTRAINT FK_238C3C39EF1A5DBF');
        $this->addSql('ALTER TABLE composant_composant DROP CONSTRAINT FK_238C3C39F6FF0D30');
        $this->addSql('ALTER TABLE plage_utilisateur DROP CONSTRAINT FK_A45A40C7F3310E7');
        $this->addSql('DROP SEQUENCE annuaire_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE composant_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE plage_utilisateur_id_seq CASCADE');
        $this->addSql('DROP TABLE annuaire');
        $this->addSql('DROP TABLE composant');
        $this->addSql('DROP TABLE composant_composant');
        $this->addSql('DROP TABLE plage_utilisateur');
        $this->addSql('ALTER TABLE motif_refus ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE motif_refus ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE nature_impact ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE nature_impact ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE motif_intervention ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE motif_intervention ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE motif_renvoi ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE motif_renvoi ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE impact_meteo ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE impact_meteo ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE profil ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE profil ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE liste_diffusion_si2a ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE liste_diffusion_si2a ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE mission ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE mission ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE usager ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE usager ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE domaine ALTER ajoute_le DROP DEFAULT');
        $this->addSql('ALTER TABLE domaine ALTER maj_le DROP DEFAULT');
        $this->addSql('ALTER TABLE type_element ALTER ajoute_le DROP DEFAULT');
    }
}
