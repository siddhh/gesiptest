<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210126084112 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajoute les entités CarteIdentite et CarteIdentiteEvenement.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE carte_identite_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE carte_identite_evenement_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE carte_identite (id INT NOT NULL, composant_id INT NOT NULL, service_id INT NOT NULL, nom_fichier VARCHAR(500) NOT NULL, taille_fichier BIGINT NOT NULL, commentaire TEXT DEFAULT NULL, transmission_service_manager BOOLEAN DEFAULT \'false\' NOT NULL, transmission_switch BOOLEAN DEFAULT \'false\' NOT NULL, transmission_sinaps BOOLEAN DEFAULT \'false\' NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F57BC5857F3310E7 ON carte_identite (composant_id)');
        $this->addSql('CREATE INDEX IDX_F57BC585ED5CA9E6 ON carte_identite (service_id)');
        $this->addSql('CREATE TABLE carte_identite_evenement (id INT NOT NULL, composant_id INT NOT NULL, service_id INT NOT NULL, evenement VARCHAR(500) NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_73BAD5F97F3310E7 ON carte_identite_evenement (composant_id)');
        $this->addSql('CREATE INDEX IDX_73BAD5F9ED5CA9E6 ON carte_identite_evenement (service_id)');
        $this->addSql('ALTER TABLE carte_identite ADD CONSTRAINT FK_F57BC5857F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE carte_identite ADD CONSTRAINT FK_F57BC585ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD CONSTRAINT FK_73BAD5F97F3310E7 FOREIGN KEY (composant_id) REFERENCES composant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD CONSTRAINT FK_73BAD5F9ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE carte_identite_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE carte_identite_evenement_id_seq CASCADE');
        $this->addSql('DROP TABLE carte_identite');
        $this->addSql('DROP TABLE carte_identite_evenement');
    }
}
