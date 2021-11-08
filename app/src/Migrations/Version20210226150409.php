<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210226150409 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Suppression de la contrainte d\'unicité en base pour les emails des services.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mep_ssi ALTER demande_par_id DROP DEFAULT');
        $this->addSql('DROP INDEX uniq_e19d9ad2e7927c74');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_e19d9ad2e7927c74 ON service (email)');
        $this->addSql('ALTER TABLE mep_ssi ALTER demande_par_id SET DEFAULT 1');
    }
}
