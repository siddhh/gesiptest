<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200717123231 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Mission';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE mission_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE mission (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE mission_id_seq CASCADE');
        $this->addSql('DROP TABLE mission');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Assistance\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'AT\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Développement\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'ESI hebergeur\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Service (pour information)\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'EA Exploitant Applicatif\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'ES Exploitant Système\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Intégration Applicative\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Intégration Inter-Applicative\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'Intégration de l\'\'Exploitabilité\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'MOA\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'MOE\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'MOA Associée\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'MOE Déléguée\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO mission (id, label, supprime_le, ajoute_le, maj_le) VALUES (nextval(\'mission_id_seq\'), \'DME\', NOW(), NOW(), NOW())');
    }
}
