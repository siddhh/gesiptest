<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210615123723 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajoute la possibilité d\'associer des exploitants exterieurs à une demande d\'intervention.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demande_intervention_service (demande_intervention_id INT NOT NULL, service_id INT NOT NULL, PRIMARY KEY(demande_intervention_id, service_id))');
        $this->addSql('CREATE INDEX IDX_5360585D7607473E ON demande_intervention_service (demande_intervention_id)');
        $this->addSql('CREATE INDEX IDX_5360585DED5CA9E6 ON demande_intervention_service (service_id)');
        $this->addSql('ALTER TABLE demande_intervention_service ADD CONSTRAINT FK_5360585D7607473E FOREIGN KEY (demande_intervention_id) REFERENCES demande_intervention (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE demande_intervention_service ADD CONSTRAINT FK_5360585DED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE demande_intervention_service');
    }
}
