<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201119162143 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout champs MeteoTransfertLe et MeteoTransfertPar';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact_reel ADD meteo_transfert_par_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_impact_reel ADD meteo_transfert_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_impact_reel ADD CONSTRAINT FK_6AC838EDF7951C9C FOREIGN KEY (meteo_transfert_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6AC838EDF7951C9C ON demande_impact_reel (meteo_transfert_par_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact_reel DROP CONSTRAINT FK_6AC838EDF7951C9C');
        $this->addSql('DROP INDEX IDX_6AC838EDF7951C9C');
        $this->addSql('ALTER TABLE demande_impact_reel DROP meteo_transfert_par_id');
        $this->addSql('ALTER TABLE demande_impact_reel DROP meteo_transfert_le');
    }
}
