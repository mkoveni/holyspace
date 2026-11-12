<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Domain\Repository\VisitorChildRepository;
use App\Events\Domain\VisitorChild;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalVisitorChildStore implements VisitorChildRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function insert(VisitorChild $child): void
    {
        $this->connection->insert('event_visitor_children', [
            'id' => $child->id,
            'registration_id' => $child->registrationId,
            'first_name' => $child->firstName,
            'position' => $child->position,
            'birth_date' => $child->birthDate->format('Y-m-d'),
            'handover_status' => VisitorChild::PENDING,
            'created_at' => Timestamps::toDatabase($child->createdAt),
        ]);
    }

    public function state(string $childId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT c.handover_status, c.registration_id, v.review_status FROM event_visitor_children c JOIN event_visitor_check_ins v ON v.registration_id = c.registration_id WHERE c.id = ? FOR UPDATE OF c',
            [$childId],
        );

        return false === $row ? null : [
            'status' => (string) $row['handover_status'],
            'registrationId' => (string) $row['registration_id'],
            'visitRemoved' => 'REMOVED' === $row['review_status'],
        ];
    }

    public function settle(string $childId, string $status, string $actorId, DateTimeImmutable $at): bool
    {
        return 1 === $this->connection->executeStatement(
            "UPDATE event_visitor_children SET handover_status = ?, handled_by = ?, handled_at = ? WHERE id = ? AND handover_status = 'PENDING'",
            [$status, $actorId, Timestamps::toDatabase($at), $childId],
        );
    }
}
