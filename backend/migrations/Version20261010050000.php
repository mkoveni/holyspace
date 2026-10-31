<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ministry and small-group rosters, leadership, and membership history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ministries_records(id CHAR(36) PRIMARY KEY, kind VARCHAR(40) NOT NULL, payload JSON NOT NULL, version INT NOT NULL, INDEX idx_ministries_kind(kind)) ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ministries_records');
    }
}
