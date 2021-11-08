<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200629152542 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Met en place la relation entre l\'entité Service et la structure principale.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service RENAME COLUMN id_structure_principale TO structure_principale_id');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD285BC0DF1 FOREIGN KEY (structure_principale_id) REFERENCES service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E19D9AD285BC0DF1 ON service (structure_principale_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service DROP CONSTRAINT FK_E19D9AD285BC0DF1');
        $this->addSql('DROP INDEX IDX_E19D9AD285BC0DF1');
        $this->addSql('ALTER TABLE service RENAME COLUMN structure_principale_id TO id_structure_principale');
    }
}
