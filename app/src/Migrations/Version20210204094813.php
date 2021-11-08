<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210204094813 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout demandePar sur MepSsi.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mep_ssi ADD demande_par_id INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE mep_ssi ADD CONSTRAINT FK_2343B6494C0C045 FOREIGN KEY (demande_par_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2343B6494C0C045 ON mep_ssi (demande_par_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mep_ssi DROP CONSTRAINT FK_2343B6494C0C045');
        $this->addSql('DROP INDEX IDX_2343B6494C0C045');
        $this->addSql('ALTER TABLE mep_ssi DROP demande_par_id');
    }
}
