<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200710102113 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Motif de Renvoi';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE motif_renvoi_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE motif_renvoi (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE motif_renvoi_id_seq CASCADE');
        $this->addSql('DROP TABLE motif_renvoi');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Composant concerné\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Nature d\'\'intervention\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Motif d\'\'interventions\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Palier applicatif\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Description\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Intervention réalisée par\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Solution de contournement existante\', \'Demande\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Période - Date Heure Intervention\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Période - Date Heure de fin min\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Période – Date Heure de fin max\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Durée retour arrière\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Nature\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Impact\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Détail impact\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Composants impactés\', \'Impact\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_renvoi (id, label, type, ajoute_le, maj_le) VALUES (nextval(\'motif_renvoi_id_seq\'), \'Ajouter un impact\', \'Impact\', NOW(), NOW())');
    }
}
