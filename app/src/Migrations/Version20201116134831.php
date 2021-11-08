<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201116134831 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout des tables permettant de stocker les informations en rapport à la Météo.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE meteo_composant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE meteo_evenement_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE meteo_publication_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE meteo_composant (id INT NOT NULL, composant_id INT NOT NULL, periode_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, periode_fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, meteo VARCHAR(255) NOT NULL, disponibilite DOUBLE PRECISION NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2A9FF51A7F3310E7 ON meteo_composant (composant_id)');
        $this->addSql('CREATE TABLE meteo_evenement (id INT NOT NULL, composant_id INT NOT NULL, impact_id INT NOT NULL, type_operation_id INT NOT NULL, saisie_par_id INT DEFAULT NULL, debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, description TEXT DEFAULT NULL, commentaire TEXT DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CD3D1BCD7F3310E7 ON meteo_evenement (composant_id)');
        $this->addSql('CREATE INDEX IDX_CD3D1BCDD128BC9B ON meteo_evenement (impact_id)');
        $this->addSql('CREATE INDEX IDX_CD3D1BCDC3EF8F86 ON meteo_evenement (type_operation_id)');
        $this->addSql('CREATE INDEX IDX_CD3D1BCDC74AC7FE ON meteo_evenement (saisie_par_id)');
        $this->addSql('CREATE TABLE meteo_publication (id INT NOT NULL, periode_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, periode_fin TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE meteo_composant ADD CONSTRAINT FK_2A9FF51A7F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meteo_evenement ADD CONSTRAINT FK_CD3D1BCD7F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meteo_evenement ADD CONSTRAINT FK_CD3D1BCDD128BC9B FOREIGN KEY (impact_id) REFERENCES impact_meteo (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meteo_evenement ADD CONSTRAINT FK_CD3D1BCDC3EF8F86 FOREIGN KEY (type_operation_id) REFERENCES motif_intervention (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meteo_evenement ADD CONSTRAINT FK_CD3D1BCDC74AC7FE FOREIGN KEY (saisie_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE meteo_composant_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE meteo_evenement_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE meteo_publication_id_seq CASCADE');
        $this->addSql('DROP TABLE meteo_composant');
        $this->addSql('DROP TABLE meteo_evenement');
        $this->addSql('DROP TABLE meteo_publication');
    }
}
