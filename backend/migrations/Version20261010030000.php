<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Giving funds, posted donations, acknowledgements, reversals, pledges, and reconciliation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE giving_records(id CHAR(36) PRIMARY KEY, kind VARCHAR(40) NOT NULL, payload JSON NOT NULL, version INT NOT NULL, INDEX idx_giving_kind(kind)) ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE giving_records');
    }
}
