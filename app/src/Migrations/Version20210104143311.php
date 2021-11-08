<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210104143311 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Passe la taille du champ Mime type de 64 à 128 caractères pour les fichiers de documentation.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fichier ALTER mime_type TYPE VARCHAR(128)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fichier ALTER mime_type TYPE VARCHAR(64)');
    }
}
