<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210322104408 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Changement de relation entre les Grid et les Mep SSI (plusieurs Grid par Mep SSI).';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mep_ssi_grid_mep (mep_ssi_id INT NOT NULL, grid_mep_id INT NOT NULL, PRIMARY KEY(mep_ssi_id, grid_mep_id))');
        $this->addSql('CREATE INDEX IDX_A4EFA6EF44406A48 ON mep_ssi_grid_mep (mep_ssi_id)');
        $this->addSql('CREATE INDEX IDX_A4EFA6EF86AF3F15 ON mep_ssi_grid_mep (grid_mep_id)');
        $this->addSql('ALTER TABLE mep_ssi_grid_mep ADD CONSTRAINT FK_A4EFA6EF44406A48 FOREIGN KEY (mep_ssi_id) REFERENCES mep_ssi (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi_grid_mep ADD CONSTRAINT FK_A4EFA6EF86AF3F15 FOREIGN KEY (grid_mep_id) REFERENCES grid_mep (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mep_ssi DROP CONSTRAINT fk_2343b6492cf16895');
        $this->addSql('DROP INDEX idx_2343b6492cf16895');
        $this->addSql('ALTER TABLE mep_ssi DROP grid_id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mep_ssi_grid_mep');
        $this->addSql('ALTER TABLE mep_ssi ADD grid_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mep_ssi ADD CONSTRAINT fk_2343b6492cf16895 FOREIGN KEY (grid_id) REFERENCES grid_mep (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2343b6492cf16895 ON mep_ssi (grid_id)');
    }
}
