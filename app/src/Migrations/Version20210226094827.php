<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210226094827 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Déplacement des commentaires, et prise en compte des composants non Gesip pour la carte d\'identité';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE composant_carte_identite_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE composant_carte_identite (id INT NOT NULL, label VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE carte_identite ADD composant_carte_identite_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE carte_identite DROP commentaire');
        $this->addSql('ALTER TABLE carte_identite ALTER composant_id DROP NOT NULL');
        $this->addSql('ALTER TABLE carte_identite ADD CONSTRAINT FK_F57BC585C9D6ED2 FOREIGN KEY (composant_carte_identite_id) REFERENCES composant_carte_identite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_F57BC585C9D6ED2 ON carte_identite (composant_carte_identite_id)');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD composant_carte_identite_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD carte_identite_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD commentaire TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE carte_identite_evenement ALTER composant_id DROP NOT NULL');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD CONSTRAINT FK_73BAD5F9C9D6ED2 FOREIGN KEY (composant_carte_identite_id) REFERENCES composant_carte_identite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE carte_identite_evenement ADD CONSTRAINT FK_73BAD5F9F0E59711 FOREIGN KEY (carte_identite_id) REFERENCES carte_identite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_73BAD5F9C9D6ED2 ON carte_identite_evenement (composant_carte_identite_id)');
        $this->addSql('CREATE INDEX IDX_73BAD5F9F0E59711 ON carte_identite_evenement (carte_identite_id)');
        $this->addSql('ALTER TABLE mep_ssi ALTER demande_par_id DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE carte_identite DROP CONSTRAINT FK_F57BC585C9D6ED2');
        $this->addSql('ALTER TABLE carte_identite_evenement DROP CONSTRAINT FK_73BAD5F9C9D6ED2');
        $this->addSql('DROP SEQUENCE composant_carte_identite_id_seq CASCADE');
        $this->addSql('DROP TABLE composant_carte_identite');
        $this->addSql('DROP INDEX IDX_F57BC585C9D6ED2');
        $this->addSql('ALTER TABLE carte_identite ADD commentaire TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE carte_identite DROP composant_carte_identite_id');
        $this->addSql('ALTER TABLE carte_identite ALTER composant_id SET NOT NULL');
        $this->addSql('ALTER TABLE mep_ssi ALTER demande_par_id SET DEFAULT 1');
        $this->addSql('ALTER TABLE carte_identite_evenement DROP CONSTRAINT FK_73BAD5F9F0E59711');
        $this->addSql('DROP INDEX IDX_73BAD5F9C9D6ED2');
        $this->addSql('DROP INDEX IDX_73BAD5F9F0E59711');
        $this->addSql('ALTER TABLE carte_identite_evenement DROP composant_carte_identite_id');
        $this->addSql('ALTER TABLE carte_identite_evenement DROP carte_identite_id');
        $this->addSql('ALTER TABLE carte_identite_evenement DROP commentaire');
        $this->addSql('ALTER TABLE carte_identite_evenement ALTER composant_id SET NOT NULL');
    }
}
