<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201118160706 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Ajout de nouvelles valeurs dans Impacts Météo et Motif d\'intervention.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->connection->executeQuery('INSERT INTO impact_meteo (id, label, ajoute_le, maj_le) VALUES (nextval(\'impact_meteo_id_seq\'), \'Retard mineur dans la mise à jour des données\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO impact_meteo (id, label, ajoute_le, maj_le) VALUES (nextval(\'impact_meteo_id_seq\'), \'Transparent pour les utilisateurs\', NOW(), NOW())');
        $this->connection->executeQuery('INSERT INTO motif_intervention (id, label, ajoute_le, maj_le) VALUES (nextval(\'motif_intervention_id_seq\'), \'Incident\', NOW(), NOW())');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->connection->executeQuery('DELETE FROM impact_meteo WHERE label = \'Retard mineur dans la mise à jour des données\'');
        $this->connection->executeQuery('DELETE FROM impact_meteo WHERE label = \'Transparent pour les utilisateurs\'');
        $this->connection->executeQuery('DELETE FROM motif_intervention WHERE label = \'Incident\'');
    }
}
