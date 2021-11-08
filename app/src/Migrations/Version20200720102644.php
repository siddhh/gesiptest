<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200720102644 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création de la table permettant d\'enregistrer les actions des utilisateurs';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE action_history_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE action_history (id INT NOT NULL, action_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip VARCHAR(15) DEFAULT NULL, service_id INT DEFAULT NULL, objet_classe VARCHAR(64) NOT NULL, objet_id INT NOT NULL, action VARCHAR(16) NOT NULL, details JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_logactions_date ON action_history (action_date)');
        $this->addSql('CREATE INDEX idx_logactions_ip ON action_history (ip)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE action_history_id_seq CASCADE');
        $this->addSql('DROP TABLE action_history');
    }
}
