<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201029150107 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Permet d\'associer des impacts réels à une demande d\'intervention (utile dans le cadre de la saisie du réalisé).';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE demande_impact_reel_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE demande_saisie_realise_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE demande_impact_reel (id INT NOT NULL, saisie_realise_id INT NOT NULL, nature_id INT NOT NULL, numero_ordre INT NOT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, commentaire TEXT DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6AC838EDA9159666 ON demande_impact_reel (saisie_realise_id)');
        $this->addSql('CREATE INDEX IDX_6AC838ED3BCB2E4B ON demande_impact_reel (nature_id)');
        $this->addSql('CREATE TABLE demande_impactreel_composant (impact_reel_id INT NOT NULL, composant_id INT NOT NULL, PRIMARY KEY(impact_reel_id, composant_id))');
        $this->addSql('CREATE INDEX IDX_6C2BFAD28E8A440D ON demande_impactreel_composant (impact_reel_id)');
        $this->addSql('CREATE INDEX IDX_6C2BFAD27F3310E7 ON demande_impactreel_composant (composant_id)');
        $this->addSql('CREATE TABLE demande_saisie_realise (id INT NOT NULL, demande_id INT NOT NULL, service_id INT DEFAULT NULL, resultat TEXT NOT NULL, commentaire TEXT DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B29AB6EF80E95E18 ON demande_saisie_realise (demande_id)');
        $this->addSql('CREATE INDEX IDX_B29AB6EFED5CA9E6 ON demande_saisie_realise (service_id)');
        $this->addSql('ALTER TABLE demande_impact_reel ADD CONSTRAINT FK_6AC838EDA9159666 FOREIGN KEY (saisie_realise_id) REFERENCES demande_saisie_realise (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impact_reel ADD CONSTRAINT FK_6AC838ED3BCB2E4B FOREIGN KEY (nature_id) REFERENCES nature_impact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impactreel_composant ADD CONSTRAINT FK_6C2BFAD28E8A440D FOREIGN KEY (impact_reel_id) REFERENCES demande_impact_reel (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_impactreel_composant ADD CONSTRAINT FK_6C2BFAD27F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_saisie_realise ADD CONSTRAINT FK_B29AB6EF80E95E18 FOREIGN KEY (demande_id) REFERENCES demande_intervention (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_saisie_realise ADD CONSTRAINT FK_B29AB6EFED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impactreel_composant DROP CONSTRAINT FK_6C2BFAD28E8A440D');
        $this->addSql('ALTER TABLE demande_impact_reel DROP CONSTRAINT FK_6AC838EDA9159666');
        $this->addSql('DROP SEQUENCE demande_impact_reel_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE demande_saisie_realise_id_seq CASCADE');
        $this->addSql('DROP TABLE demande_impact_reel');
        $this->addSql('DROP TABLE demande_impactreel_composant');
        $this->addSql('DROP TABLE demande_saisie_realise');
    }
}
