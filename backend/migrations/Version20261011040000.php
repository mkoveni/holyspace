<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Database queue positions preserve registration order across PHP workers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_registrations ADD queue_position BIGINT NOT NULL DEFAULT 0');
        $this->addSql("UPDATE event_registrations r JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY occurrence_id ORDER BY queue_token) AS position FROM event_registrations) ranked ON ranked.id = r.id SET r.queue_position = ranked.position, r.payload = JSON_SET(r.payload, '$.queuePosition', ranked.position)");
        $this->addSql('ALTER TABLE event_registrations DROP INDEX idx_registration_queue, DROP INDEX idx_registration_waitlist, ADD INDEX idx_registration_queue(occurrence_id, queue_position), ADD INDEX idx_registration_waitlist(occurrence_id, status, queue_position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE event_registrations SET payload = JSON_REMOVE(payload, '$.queuePosition')");
        $this->addSql('ALTER TABLE event_registrations DROP INDEX idx_registration_queue, DROP INDEX idx_registration_waitlist, DROP queue_position, ADD INDEX idx_registration_queue(occurrence_id, queue_token), ADD INDEX idx_registration_waitlist(occurrence_id, status, queue_token)');
    }
}
