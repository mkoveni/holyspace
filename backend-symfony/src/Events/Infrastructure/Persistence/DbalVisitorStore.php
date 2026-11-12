<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\VisitorChildRow;
use App\Events\Application\Contract\VisitorReader;
use App\Events\Application\Contract\VisitorRow;
use App\Events\Domain\Repository\VisitorCheckInRepository;
use App\Events\Domain\VisitorCheckIn;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalVisitorStore implements VisitorCheckInRepository, VisitorReader
{
    private const string ROWS = "SELECT v.*, COALESCE(a.status, CASE WHEN r.status = 'WAITLISTED' THEN 'WAITLISTED' ELSE 'NONE' END) AS attendance_status, a.version AS attendance_version
        FROM event_visitor_check_ins v
        JOIN event_registrations r ON r.id = v.registration_id
        LEFT JOIN event_attendance a ON a.registration_id = v.registration_id";

    public function __construct(private Connection $connection)
    {
    }

    public function insert(VisitorCheckIn $visit): void
    {
        $this->connection->insert('event_visitor_check_ins', [
            'registration_id' => $visit->registrationId,
            'occurrence_id' => $visit->occurrenceId,
            'full_name' => $visit->fullName,
            'mobile' => $visit->mobile,
            'email' => $visit->email,
            'how_heard' => $visit->howHeard,
            'party_adults' => $visit->partyAdults,
            'first_time' => $visit->firstTime,
            'consent_version' => $visit->consentVersion,
            'consented_at' => Timestamps::toDatabase($visit->consentedAt),
            'review_status' => $visit->reviewStatus,
            'created_at' => Timestamps::toDatabase($visit->createdAt),
        ], ['first_time' => ParameterType::BOOLEAN]);
    }

    public function exists(string $registrationId): bool
    {
        return null !== $this->reviewStatus($registrationId);
    }

    public function reviewStatus(string $registrationId): ?string
    {
        $status = $this->connection->fetchOne('SELECT review_status FROM event_visitor_check_ins WHERE registration_id = ?', [$registrationId]);

        return false === $status ? null : (string) $status;
    }

    public function review(string $registrationId, string $status, string $actorId, DateTimeImmutable $at): bool
    {
        return 1 === $this->connection->executeStatement(
            'UPDATE event_visitor_check_ins SET review_status = ?, reviewed_by = ?, reviewed_at = ? WHERE registration_id = ? AND review_status <> ?',
            [$status, $actorId, Timestamps::toDatabase($at), $registrationId, $status],
        );
    }

    public function forOccurrence(string $occurrenceId, ?string $reviewStatus, int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            self::ROWS.' WHERE v.occurrence_id = :occurrence AND (CAST(:status AS VARCHAR) IS NULL OR v.review_status = :status)
             ORDER BY v.created_at DESC, v.registration_id LIMIT :limit OFFSET :offset',
            ['occurrence' => $occurrenceId, 'status' => $reviewStatus, 'limit' => $limit, 'offset' => $offset],
        );

        $children = $this->children(array_map(static fn (array $row): string => (string) $row['registration_id'], $rows));

        return array_map(static fn (array $row): VisitorRow => self::row($row, $children[(string) $row['registration_id']] ?? []), $rows);
    }

    public function count(string $occurrenceId, ?string $reviewStatus): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT count(*) FROM event_visitor_check_ins WHERE occurrence_id = :occurrence AND (CAST(:status AS VARCHAR) IS NULL OR review_status = :status)',
            ['occurrence' => $occurrenceId, 'status' => $reviewStatus],
        );
    }

    public function find(string $registrationId): ?VisitorRow
    {
        $row = $this->connection->fetchAssociative(self::ROWS.' WHERE v.registration_id = ?', [$registrationId]);

        return false === $row ? null : self::row($row, $this->children([$registrationId])[$registrationId] ?? []);
    }

    public function personId(string $registrationId): ?string
    {
        $id = $this->connection->fetchOne('SELECT r.person_id FROM event_visitor_check_ins v JOIN event_registrations r ON r.id = v.registration_id WHERE v.registration_id = ?', [$registrationId]);

        return false === $id ? null : (string) $id;
    }

    /**
     * @param list<string> $registrationIds
     *
     * @return array<string, list<VisitorChildRow>>
     */
    private function children(array $registrationIds): array
    {
        if ([] === $registrationIds) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, registration_id, first_name, birth_date, handover_status FROM event_visitor_children WHERE registration_id IN (?) ORDER BY registration_id, position',
            [$registrationIds],
            [ArrayParameterType::STRING],
        );
        $byVisit = [];
        foreach ($rows as $row) {
            $byVisit[(string) $row['registration_id']][] = new VisitorChildRow((string) $row['id'], (string) $row['first_name'], new DateTimeImmutable((string) $row['birth_date']), (string) $row['handover_status']);
        }

        return $byVisit;
    }

    /**
     * @param array<string, mixed>  $row
     * @param list<VisitorChildRow> $children
     */
    private static function row(array $row, array $children): VisitorRow
    {
        return new VisitorRow(
            (string) $row['registration_id'],
            (string) $row['occurrence_id'],
            (string) $row['full_name'],
            null === $row['mobile'] ? null : (string) $row['mobile'],
            null === $row['email'] ? null : (string) $row['email'],
            null === $row['how_heard'] ? null : (string) $row['how_heard'],
            null === $row['party_adults'] ? null : (int) $row['party_adults'],
            (bool) $row['first_time'],
            (string) $row['review_status'],
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['attendance_status'],
            null === $row['attendance_version'] ? null : (int) $row['attendance_version'],
            $children,
        );
    }
}
