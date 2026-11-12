<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\VisitMessagingReader;
use App\Events\Domain\Repository\VisitMessagingRepository;
use App\Events\Domain\Repository\VisitorMessageRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalVisitMessagingStore implements VisitMessagingRepository, VisitorMessageRepository, VisitMessagingReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function approve(string $occurrenceId, string $actorId, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_visit_messaging (occurrence_id, approved_by, approved_at) VALUES (?, ?, ?)
             ON CONFLICT (occurrence_id) DO UPDATE SET approved_by = EXCLUDED.approved_by, approved_at = EXCLUDED.approved_at',
            [$occurrenceId, $actorId, Timestamps::toDatabase($at)],
        );
    }

    public function withdraw(string $occurrenceId): void
    {
        $this->connection->executeStatement('DELETE FROM event_visit_messaging WHERE occurrence_id = ?', [$occurrenceId]);
    }

    public function record(string $registrationId, string $purpose, string $deliveryId, string $reviewHash, DateTimeImmutable $at): void
    {
        $this->connection->insert('event_visitor_messages', [
            'registration_id' => $registrationId,
            'purpose' => $purpose,
            'delivery_id' => $deliveryId,
            'review_hash' => $reviewHash,
            'created_at' => Timestamps::toDatabase($at),
        ]);
    }

    public function sent(string $registrationId, string $purpose): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM event_visitor_messages WHERE registration_id = ? AND purpose = ?', [$registrationId, $purpose]);
    }

    public function approver(string $occurrenceId): ?string
    {
        $id = $this->connection->fetchOne('SELECT approved_by FROM event_visit_messaging WHERE occurrence_id = ?', [$occurrenceId]);

        return false === $id ? null : (string) $id;
    }

    public function message(string $deliveryId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT registration_id, purpose, review_hash FROM event_visitor_messages WHERE delivery_id = ?', [$deliveryId]);

        return false === $row ? null : ['registrationId' => (string) $row['registration_id'], 'purpose' => (string) $row['purpose'], 'reviewHash' => (string) $row['review_hash']];
    }
}
