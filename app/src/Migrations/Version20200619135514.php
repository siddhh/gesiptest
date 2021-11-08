<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200619135514 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout de 5 champs à entité Service';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE service ADD est_service_exploitant BOOLEAN NOT NULL DEFAULT \'FALSE\'');
        $this->addSql('ALTER TABLE service ADD est_bureau_rattachement BOOLEAN NOT NULL DEFAULT \'FALSE\'');
        $this->addSql('ALTER TABLE service ADD est_structure_rattachement BOOLEAN NOT NULL DEFAULT \'FALSE\'');
        $this->addSql('ALTER TABLE service ADD est_pilotage_dme BOOLEAN NOT NULL DEFAULT \'FALSE\'');
        $this->addSql('ALTER TABLE service ADD id_structure_principale INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ALTER reset_motdepasse SET DEFAULT \'false\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE service DROP est_service_exploitant');
        $this->addSql('ALTER TABLE service DROP est_bureau_rattachement');
        $this->addSql('ALTER TABLE service DROP est_structure_rattachement');
        $this->addSql('ALTER TABLE service DROP est_pilotage_dme');
        $this->addSql('ALTER TABLE service DROP id_structure_principale');
        $this->addSql('ALTER TABLE service ALTER reset_motdepasse DROP DEFAULT');
    }
}
