<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Application\Contract\ReminderReader;
use App\Volunteering\Application\Contract\ReminderRecord;
use App\Volunteering\Domain\Repository\ReminderRepository;
use App\Volunteering\Domain\VolunteerReminder;
use Doctrine\DBAL\Connection;

/** Java `JooqReminderStore` (`serving_reminders`). */
final readonly class DbalReminderStore implements ReminderReader, ReminderRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function reminder(string $id): ?ReminderRecord
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'assignment_id', 'assignment_version', 'shift_version', 'contact_id', 'review_hash', 'verification_evidence', 'created_by', 'created_at', 'delivery_id')
            ->from('serving_reminders')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : new ReminderRecord(
            (string) $row['id'],
            (string) $row['assignment_id'],
            (int) $row['assignment_version'],
            (int) $row['shift_version'],
            (string) $row['contact_id'],
            (string) $row['review_hash'],
            (string) $row['verification_evidence'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['delivery_id'],
        );
    }

    public function insert(VolunteerReminder $reminder): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_reminders', [
            'id' => $reminder->id,
            'assignment_id' => $reminder->assignmentId,
            'assignment_version' => $reminder->assignmentVersion,
            'shift_version' => $reminder->shiftVersion,
            'contact_id' => $reminder->contactId,
            'review_hash' => $reminder->reviewHash,
            'verification_evidence' => $reminder->evidence,
            'created_by' => $reminder->createdBy,
            'created_at' => Timestamps::toDatabase($reminder->createdAt),
            'delivery_id' => $reminder->deliveryId,
        ]));
    }
}
