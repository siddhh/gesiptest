<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210126151823 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Migration de MeteoTransfertLe/Par en ImpactReel dans MeteoEvenement.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact_reel DROP CONSTRAINT fk_6ac838edf7951c9c');
        $this->addSql('DROP INDEX idx_6ac838edf7951c9c');
        $this->addSql('ALTER TABLE demande_impact_reel DROP meteo_transfert_par_id');
        $this->addSql('ALTER TABLE demande_impact_reel DROP meteo_transfert_le');
        $this->addSql('ALTER TABLE meteo_evenement ADD impacts_reel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE meteo_evenement ADD CONSTRAINT FK_CD3D1BCD38EBCA14 FOREIGN KEY (impacts_reel_id) REFERENCES demande_impact_reel (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CD3D1BCD38EBCA14 ON meteo_evenement (impacts_reel_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact_reel ADD meteo_transfert_par_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_impact_reel ADD meteo_transfert_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_impact_reel ADD CONSTRAINT fk_6ac838edf7951c9c FOREIGN KEY (meteo_transfert_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_6ac838edf7951c9c ON demande_impact_reel (meteo_transfert_par_id)');
        $this->addSql('ALTER TABLE meteo_evenement DROP CONSTRAINT FK_CD3D1BCD38EBCA14');
        $this->addSql('DROP INDEX IDX_CD3D1BCD38EBCA14');
        $this->addSql('ALTER TABLE meteo_evenement DROP impacts_reel_id');
    }
}
