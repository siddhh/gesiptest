<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201228151523 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Retrait du champ publieLe et ajout du champ supprimeLe de \'entité Document.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document RENAME COLUMN publie_le TO supprime_le');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document RENAME COLUMN supprime_le TO publie_le');
    }
}
