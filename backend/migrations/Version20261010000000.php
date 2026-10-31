<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Church events and stable, independently editable scheduled occurrences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE church_events (id CHAR(36) NOT NULL, title VARCHAR(200) NOT NULL, type VARCHAR(30) NOT NULL, description TEXT DEFAULT NULL, timezone VARCHAR(100) NOT NULL, recurrence VARCHAR(20) NOT NULL, created_at VARCHAR(35) NOT NULL, updated_at VARCHAR(35) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE church_event_occurrences (id CHAR(36) NOT NULL, event_id CHAR(36) NOT NULL, starts_at VARCHAR(35) NOT NULL, ends_at VARCHAR(35) NOT NULL, location VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, created_at VARCHAR(35) NOT NULL, updated_at VARCHAR(35) NOT NULL, INDEX idx_occurrence_event (event_id), INDEX idx_occurrence_start (starts_at), PRIMARY KEY(id), CONSTRAINT fk_occurrence_event FOREIGN KEY (event_id) REFERENCES church_events (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE church_event_occurrences');
        $this->addSql('DROP TABLE church_events');
    }
}
