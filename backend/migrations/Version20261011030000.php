<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-occurrence registrations, capacity, ordered waitlists and durable cancellation history for one church.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_registration_settings(id CHAR(36) PRIMARY KEY, payload JSON NOT NULL, version INT NOT NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addSql('CREATE TABLE event_registrations(id CHAR(36) PRIMARY KEY, occurrence_id CHAR(36) NOT NULL, person_id CHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, queue_token CHAR(36) NOT NULL, payload JSON NOT NULL, version INT NOT NULL, UNIQUE INDEX uniq_registration_person(occurrence_id, person_id), INDEX idx_registration_queue(occurrence_id, queue_token), INDEX idx_registration_waitlist(occurrence_id, status, queue_token)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_registrations');
        $this->addSql('DROP TABLE event_registration_settings');
    }
}
