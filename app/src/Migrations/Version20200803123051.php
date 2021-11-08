<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200803123051 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table Demande Referentiel Flux.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE demande_referentiel_flux_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE demande_referentiel_flux (id INT NOT NULL, service_demandeur_id INT NOT NULL, composant_source_id INT NOT NULL, composant_target_id INT NOT NULL, type VARCHAR(10) NOT NULL, commentaire TEXT DEFAULT NULL, accepte_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, accepte_par_id INT DEFAULT NULL, refuse_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, refuse_par_id INT DEFAULT NULL, annule_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, annule_par_id INT DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BCFF2D674A0A9BBC ON demande_referentiel_flux (service_demandeur_id)');
        $this->addSql('CREATE INDEX IDX_BCFF2D675A34A691 ON demande_referentiel_flux (composant_source_id)');
        $this->addSql('CREATE INDEX IDX_BCFF2D67DA86B196 ON demande_referentiel_flux (composant_target_id)');
        $this->addSql('CREATE INDEX IDX_BCFF2D67ECC92243 ON demande_referentiel_flux (accepte_par_id)');
        $this->addSql('CREATE INDEX IDX_BCFF2D67BED2696A ON demande_referentiel_flux (refuse_par_id)');
        $this->addSql('CREATE INDEX IDX_BCFF2D67F376B95 ON demande_referentiel_flux (annule_par_id)');
        $this->addSql('ALTER TABLE demande_referentiel_flux ADD CONSTRAINT FK_BCFF2D674A0A9BBC FOREIGN KEY (service_demandeur_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_referentiel_flux ADD CONSTRAINT FK_BCFF2D675A34A691 FOREIGN KEY (composant_source_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_referentiel_flux ADD CONSTRAINT FK_BCFF2D67DA86B196 FOREIGN KEY (composant_target_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_referentiel_flux ADD CONSTRAINT FK_BCFF2D67ECC92243 FOREIGN KEY (accepte_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_referentiel_flux ADD CONSTRAINT FK_BCFF2D67BED2696A FOREIGN KEY (refuse_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_referentiel_flux ADD CONSTRAINT FK_BCFF2D67F376B95 FOREIGN KEY (annule_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE demande_referentiel_flux_id_seq CASCADE');
        $this->addSql('DROP TABLE demande_referentiel_flux');
    }
}
