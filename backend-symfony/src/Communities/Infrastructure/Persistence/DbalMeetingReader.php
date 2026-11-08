<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure\Persistence;

use App\Communities\Application\Contract\AttendanceReport;
use App\Communities\Application\Contract\MeetingReader;
use App\Communities\Application\Contract\MeetingView;
use App\Communities\Application\Contract\RosterEntryView;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Meeting and roster read models over V18; person names come from People's published directory. */
final readonly class DbalMeetingReader implements MeetingReader
{
    private const string UNAVAILABLE = 'Unavailable person';

    public function __construct(private Connection $connection, private PersonDirectory $people)
    {
    }

    public function meeting(string $id): ?MeetingView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from('community_meetings')->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : self::toMeeting($row);
    }

    public function meetings(string $communityId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('community_meetings')
            ->where('community_id = :community')
            ->setParameter('community', $communityId)
            ->orderBy('scheduled_at', 'DESC')
            ->addOrderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();

        return array_values(array_map(self::toMeeting(...), $rows));
    }

    public function meetingCount(string $communityId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('count(*)')
            ->from('community_meetings')
            ->where('community_id = :community')
            ->setParameter('community', $communityId)
            ->fetchOne();
    }

    public function roster(string $meetingId, int $limit, int $offset): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('community_roster')
            ->where('meeting_id = :meeting')
            ->setParameter('meeting', $meetingId)
            ->orderBy('id')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->fetchAllAssociative();
        $names = [];
        foreach ($this->people->getAll(array_values(array_unique(array_map(static fn (array $r): string => (string) $r['person_id'], $rows)))) as $person) {
            $names[$person->id] = $person->name;
        }

        return array_values(array_map(static fn (array $r): RosterEntryView => new RosterEntryView(
            (string) $r['id'],
            (string) $r['meeting_id'],
            (string) $r['person_id'],
            null === $r['membership_id'] ? null : (string) $r['membership_id'],
            (bool) $r['guest'],
            (string) $r['status'],
            (int) $r['version'],
            $names[(string) $r['person_id']] ?? self::UNAVAILABLE,
        ), $rows));
    }

    public function rosterCount(string $meetingId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('count(*)')
            ->from('community_roster')
            ->where('meeting_id = :meeting')
            ->setParameter('meeting', $meetingId)
            ->fetchOne();
    }

    public function reports(array $meetingIds): array
    {
        if ([] === $meetingIds) {
            return [];
        }
        $rows = $this->connection->createQueryBuilder()
            ->select(
                'm.id',
                'count(r.id) AS expected',
                "count(*) FILTER (WHERE r.status = 'PRESENT') AS present",
                "count(*) FILTER (WHERE r.status = 'ABSENT') AS absent",
                "count(*) FILTER (WHERE r.status = 'EXCUSED') AS excused",
                "count(*) FILTER (WHERE r.status = 'UNRECORDED') AS unrecorded",
                'count(*) FILTER (WHERE r.guest) AS guests',
            )
            ->from('community_meetings', 'm')
            ->leftJoin('m', 'community_roster', 'r', 'r.meeting_id = m.id')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $meetingIds, ArrayParameterType::STRING)
            ->groupBy('m.id')
            ->fetchAllAssociative();
        $reports = [];
        foreach ($rows as $r) {
            $reports[(string) $r['id']] = new AttendanceReport((string) $r['id'], (int) $r['expected'], (int) $r['present'], (int) $r['absent'], (int) $r['excused'], (int) $r['unrecorded'], (int) $r['guests']);
        }

        return $reports;
    }

    /** @param array<string, mixed> $row */
    private static function toMeeting(array $row): MeetingView
    {
        return new MeetingView(
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
