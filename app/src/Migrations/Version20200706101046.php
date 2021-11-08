<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200706101046 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajoute la table type_element et ses 3 valeurs par défaut';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE type_element_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE type_element (id INT NOT NULL, label VARCHAR(255) NOT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE type_element_id_seq CASCADE');
        $this->addSql('DROP TABLE type_element');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO type_element (id, label, ajoute_le) VALUES (1, \'Standard\', NOW())');
        $this->connection->executeQuery('INSERT INTO type_element (id, label, ajoute_le) VALUES (2, \'Non MOA - Admin\', NOW())');
        $this->connection->executeQuery('INSERT INTO type_element (id, label, ajoute_le) VALUES (3, \'Non MOA - Standard\', NOW())');
    }
}
