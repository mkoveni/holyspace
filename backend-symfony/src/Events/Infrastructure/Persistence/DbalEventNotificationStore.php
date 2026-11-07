<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\EventNotificationReader;
use App\Events\Domain\EventNotification;
use App\Events\Domain\Repository\EventNotificationRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

final readonly class DbalEventNotificationStore implements EventNotificationReader, EventNotificationRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function notification(string $id): ?EventNotification
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM event_notifications WHERE id = ?', [$id]);

        return false === $row ? null : new EventNotification(
            (string) $row['id'],
            (string) $row['registration_id'],
            (int) $row['registration_version'],
            (int) $row['schedule_revision'],
            (int) $row['event_version'],
            (string) $row['purpose'],
            (string) $row['contact_id'],
            trim((string) $row['review_hash']),
            trim((string) $row['request_hash']),
            (string) $row['verification_evidence'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['delivery_id'],
        );
    }

    public function insert(EventNotification $notification): void
    {
        $this->connection->insert('event_notifications', [
            'id' => $notification->id,
            'registration_id' => $notification->registrationId,
            'registration_version' => $notification->registrationVersion,
            'schedule_revision' => $notification->scheduleRevision,
            'event_version' => $notification->eventVersion,
            'purpose' => $notification->purpose,
            'contact_id' => $notification->contactId,
            'review_hash' => $notification->reviewHash,
            'request_hash' => $notification->requestHash,
            'verification_evidence' => $notification->evidence,
            'created_by' => $notification->createdBy,
            'created_at' => Timestamps::toDatabase($notification->createdAt),
            'delivery_id' => $notification->deliveryId,
        ]);
    }
}
