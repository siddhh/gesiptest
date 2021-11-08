<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210615123720 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajoute 2 nouvelles missions (EIM et EOM).';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Exploitant des Outils Mutualisés\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Exploitants des Infrastructures Mutualisées\', NOW(), NOW())');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->connection->executeQuery('DELETE FROM mission WHERE label IN (\'Exploitant des Outils Mutualisés\', \'Exploitants des Infrastructures Mutualisées\')');
    }
}
