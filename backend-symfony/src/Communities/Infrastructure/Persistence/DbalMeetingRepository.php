<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Domain\CommunityException;
use App\Communities\Domain\GroupMeeting;
use App\Communities\Domain\Repository\MeetingRepository;
use App\Communities\Domain\RosterEntry;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/** Group meetings and roster rows over V18 (`community_meetings`, `community_roster`). */
final readonly class DbalMeetingRepository implements MeetingRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findMeetingUnlocked(string $id): ?GroupMeeting
    {
        $row = $this->select('community_meetings')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::meeting($row);
    }

    public function lockMeeting(string $id): ?GroupMeeting
    {
        $row = $this->select('community_meetings')->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : self::meeting($row);
    }

    public function insertMeeting(GroupMeeting $meeting): void
    {
        $this->guard(fn (): int|string => $this->connection->insert('community_meetings', [
            'id' => $meeting->id,
            'community_id' => $meeting->communityId,
            'occurrence_id' => $meeting->occurrenceId,
            'scheduled_at' => Timestamps::toDatabase($meeting->scheduledAt),
            'status' => $meeting->status,
            'roster_frozen' => $meeting->rosterFrozen,
            'version' => $meeting->version,
        ], ['roster_frozen' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER]));
    }

    public function saveMeeting(GroupMeeting $meeting): void
    {
        $this->saved($this->guard(fn (): int|string => $this->connection->update('community_meetings', [
            'status' => $meeting->status,
            'roster_frozen' => $meeting->rosterFrozen,
            'version' => $meeting->version,
        ], ['id' => $meeting->id, 'version' => $meeting->version - 1], ['roster_frozen' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER])));
    }

    public function findRosterEntryUnlocked(string $id): ?RosterEntry
    {
        $row = $this->select('community_roster')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : RosterEntry::reconstitute(
            (string) $row['id'],
            (string) $row['meeting_id'],
            (string) $row['person_id'],
            null === $row['membership_id'] ? null : (string) $row['membership_id'],
            (bool) $row['guest'],
            (string) $row['status'],
            (int) $row['version'],
        );
    }

    public function addToRoster(RosterEntry $entry): void
    {
        // ON CONFLICT is not expressible with the query builder.
        $this->guard(fn (): int|string => $this->connection->executeStatement(
            'INSERT INTO community_roster (id, meeting_id, person_id, membership_id, guest, status, version) VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT (meeting_id, person_id) DO NOTHING',
            [$entry->id, $entry->meetingId, $entry->personId, $entry->membershipId, $entry->guest, $entry->status, $entry->version],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::INTEGER],
        ));
    }

    public function saveRosterEntry(RosterEntry $entry): void
    {
        $this->saved($this->guard(fn (): int|string => $this->connection->update('community_roster', [
            'status' => $entry->status,
            'version' => $entry->version,
        ], ['id' => $entry->id, 'version' => $entry->version - 1], ['version' => ParameterType::INTEGER])));
    }

    public function rosterSize(string $meetingId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('count(*)')
            ->from('community_roster')
            ->where('meeting_id = :meeting')
            ->setParameter('meeting', $meetingId)
            ->fetchOne();
    }

    private function select(string $table): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from($table);
    }

    /** @param callable(): (int|string) $write */
    private function guard(callable $write): int
    {
        try {
            return (int) $write();
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw CommunityException::clash();
            }
            throw $error;
        }
    }

    private function saved(int $rows): void
    {
        if (1 !== $rows) {
            throw CommunityException::conflict('These details changed. Refresh and review before saving.');
        }
    }

    /** @param array<string, mixed> $row */
    private static function meeting(array $row): GroupMeeting
    {
        return GroupMeeting::reconstitute(
            (string) $row['id'],
            (string) $row['community_id'],
            (string) $row['occurrence_id'],
            Timestamps::fromDatabase((string) $row['scheduled_at']),
            (string) $row['status'],
            (bool) $row['roster_frozen'],
            (int) $row['version'],
        );
    }
}
