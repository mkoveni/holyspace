<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\Invitation;
use App\Volunteering\Domain\Repository\RosterRepository;
use App\Volunteering\Domain\Shift;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Java `JooqRosterStore` writes. Every save names the previous version, so a lost race is a conflict. */
final readonly class DbalRosterStore implements RosterRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function lockShift(string $id): ?Shift
    {
        $row = $this->locked('serving_shifts', $id);

        return null === $row ? null : Shift::reconstitute(
            (string) $row['id'],
            (string) $row['request_id'],
            (string) $row['request_hash'],
            (string) $row['team_id'],
            (string) $row['role_id'],
            (string) $row['source_kind'],
            null === $row['source_id'] ? null : (string) $row['source_id'],
            null === $row['source_revision'] ? null : (string) $row['source_revision'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (int) $row['positions'],
            (string) $row['status'],
            (int) $row['version'],
            (int) $row['schedule_revision'],
        );
    }

    public function lockAssignment(string $id): ?Assignment
    {
        $row = $this->locked('serving_assignments', $id);

        return null === $row ? null : Assignment::reconstitute(
            (string) $row['id'],
            (string) $row['request_id'],
            (string) $row['request_hash'],
            (string) $row['shift_id'],
            (string) $row['volunteer_id'],
            (string) $row['status'],
            (int) $row['version'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function lockInvitation(string $id): ?Invitation
    {
        $row = $this->locked('serving_invitations', $id);

        return null === $row ? null : Invitation::reconstitute(
            (string) $row['id'],
            (string) $row['assignment_id'],
            (int) $row['assignment_version'],
            (int) $row['shift_version'],
            Timestamps::fromDatabase((string) $row['expires_at']),
            (string) $row['status'],
            (int) $row['version'],
            null === $row['responded_at'] ? null : Timestamps::fromDatabase((string) $row['responded_at']),
        );
    }

    public function insertShift(Shift $shift): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_shifts', [
            'id' => $shift->id,
            'request_id' => $shift->requestId,
            'request_hash' => $shift->requestHash,
            'team_id' => $shift->teamId,
            'role_id' => $shift->roleId,
            'source_kind' => $shift->sourceKind,
            'source_id' => $shift->sourceId,
            ...$this->shiftColumns($shift),
        ], self::VERSIONED));
    }

    public function saveShift(Shift $shift): void
    {
        $this->save('serving_shifts', $shift->id, $shift->version, $this->shiftColumns($shift));
    }

    public function insertAssignment(Assignment $assignment): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_assignments', [
            'id' => $assignment->id,
            'request_id' => $assignment->requestId,
            'request_hash' => $assignment->requestHash,
            'shift_id' => $assignment->shiftId,
            'volunteer_id' => $assignment->volunteerId,
            'status' => $assignment->status,
            'version' => $assignment->version,
            'created_by' => $assignment->createdBy,
            'created_at' => Timestamps::toDatabase($assignment->createdAt),
        ], self::VERSIONED));
    }

    public function saveAssignment(Assignment $assignment): void
    {
        $this->save('serving_assignments', $assignment->id, $assignment->version, ['status' => $assignment->status, 'version' => $assignment->version]);
    }

    public function insertInvitation(Invitation $invitation): void
    {
        ServingWrites::guard(fn (): int|string => $this->connection->insert('serving_invitations', [
            'id' => $invitation->id,
            'assignment_id' => $invitation->assignmentId,
            'assignment_version' => $invitation->assignmentVersion,
            'shift_version' => $invitation->shiftVersion,
            ...$this->invitationColumns($invitation),
        ], self::VERSIONED));
    }

    public function saveInvitation(Invitation $invitation): void
    {
        $this->save('serving_invitations', $invitation->id, $invitation->version, $this->invitationColumns($invitation));
    }

    public function event(string $id, string $assignmentId, string $actorId, string $action, string $reason, DateTimeImmutable $at): void
    {
        $this->connection->insert('serving_assignment_events', [
            'id' => $id,
            'assignment_id' => $assignmentId,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'occurred_at' => Timestamps::toDatabase($at),
        ]);
    }

    private const array VERSIONED = ['version' => ParameterType::INTEGER, 'positions' => ParameterType::INTEGER];

    /** @return array<string, mixed>|null */
    private function locked(string $table, string $id): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($table)
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : $row;
    }

    /** @param array<string, mixed> $columns */
    private function save(string $table, string $id, int $version, array $columns): void
    {
        ServingWrites::saved(ServingWrites::guard(fn (): int|string => $this->connection->update($table, $columns, ['id' => $id, 'version' => $version - 1], self::VERSIONED)));
    }

    /** @return array<string, mixed> */
    private function shiftColumns(Shift $shift): array
    {
        return [
            'source_revision' => $shift->sourceRevision,
            'starts_at' => Timestamps::toDatabase($shift->startsAt),
            'ends_at' => Timestamps::toDatabase($shift->endsAt),
            'positions' => $shift->positions,
            'status' => $shift->status,
            'version' => $shift->version,
            'schedule_revision' => $shift->scheduleRevision,
        ];
    }

    /** @return array<string, mixed> */
    private function invitationColumns(Invitation $invitation): array
    {
        return [
            'expires_at' => Timestamps::toDatabase($invitation->expiresAt),
            'status' => $invitation->status,
            'version' => $invitation->version,
            'responded_at' => null === $invitation->respondedAt ? null : Timestamps::toDatabase($invitation->respondedAt),
        ];
    }
}
