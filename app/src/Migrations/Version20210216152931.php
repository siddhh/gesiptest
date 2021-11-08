<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210216152931 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout d\'une référence vers un impact prévisionnel dans les météo évènements.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE meteo_evenement ADD impacts_previsionnel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE meteo_evenement ADD CONSTRAINT FK_CD3D1BCDD382AD3 FOREIGN KEY (impacts_previsionnel_id) REFERENCES demande_impact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CD3D1BCDD382AD3 ON meteo_evenement (impacts_previsionnel_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE meteo_evenement DROP CONSTRAINT FK_CD3D1BCDD382AD3');
        $this->addSql('DROP INDEX IDX_CD3D1BCDD382AD3');
        $this->addSql('ALTER TABLE meteo_evenement DROP impacts_previsionnel_id');
    }
}
