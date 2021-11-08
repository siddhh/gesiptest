<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201005071928 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Autorise des dates nulles pour un impact (choix "aucun impact")';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact ALTER date_debut DROP NOT NULL');
        $this->addSql('ALTER TABLE demande_impact ALTER date_fin_mini DROP NOT NULL');
        $this->addSql('ALTER TABLE demande_impact ALTER date_fin_max DROP NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_impact ALTER date_debut SET NOT NULL');
        $this->addSql('ALTER TABLE demande_impact ALTER date_fin_mini SET NOT NULL');
        $this->addSql('ALTER TABLE demande_impact ALTER date_fin_max SET NOT NULL');
    }
}
