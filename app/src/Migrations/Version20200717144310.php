<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200717144310 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table des références Domaine';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE domaine_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE domaine (id INT NOT NULL, label VARCHAR(255) NOT NULL, supprime_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, maj_le TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE domaine_id_seq CASCADE');
        $this->addSql('DROP TABLE domaine');
    }

    public function postUp(Schema $schema) : void
    {
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Domaine – Gestion du domaine\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Fiscalité – Contrôle fiscal et Contentieux\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Fiscalité – Foncier et Patrimoine\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Fiscalité – Particuliers\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Fiscalité – Professionnels\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Fiscalité – Recouvrement\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Comptabilité\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Dépenses de l\'\'Etat et Paie\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Fonds déposés\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Gestion comptable et financière\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Moyens de paiement\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Retraites et pensions\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Gestion publique – Valorisation et conseil\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Pilotage – Audit, Risques et Contrôle de gestion \', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Pilotage – Communication\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'SSI – Infrastructures\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'SSI – Outillage\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Transverse – Budget, Moyens et Logistique\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Transverse – Outillage\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Transverse – RH\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO domaine (id, label, ajoute_le, maj_le) VALUES (nextval(\'domaine_id_seq\'), \'Transverse – Référentiels\', NOW(), NOW())');
    }
}
