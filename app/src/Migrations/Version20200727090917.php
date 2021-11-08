<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200727090917 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Création des tables permettants l\'utilisation de tâches CRON';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE cron_job_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE cron_job_result_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE cron_job (id BIGINT NOT NULL, command VARCHAR(255) NOT NULL, arguments VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, running_instances INT DEFAULT 0 NOT NULL, max_instances INT DEFAULT 1 NOT NULL, number INT DEFAULT 1 NOT NULL, period VARCHAR(255) NOT NULL, last_use TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, next_run TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, enable BOOLEAN DEFAULT \'true\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE cron_job_result (id BIGINT NOT NULL, cron_job_id BIGINT NOT NULL, run_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, run_time DOUBLE PRECISION NOT NULL, status_code INT NOT NULL, output TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2CD346EE79099ED8 ON cron_job_result (cron_job_id)');
        $this->addSql('ALTER TABLE cron_job_result ADD CONSTRAINT FK_2CD346EE79099ED8 FOREIGN KEY (cron_job_id) REFERENCES cron_job (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cron_job_result DROP CONSTRAINT FK_2CD346EE79099ED8');
        $this->addSql('DROP SEQUENCE cron_job_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE cron_job_result_id_seq CASCADE');
        $this->addSql('DROP TABLE cron_job');
        $this->addSql('DROP TABLE cron_job_result');
    }
}
