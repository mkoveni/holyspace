<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\MemberBooking;
use App\Events\Application\Contract\MemberEventItem;
use App\Events\Application\Contract\MemberEventReader;
use App\Events\Application\Contract\MemberOffer;
use App\Events\Domain\Repository\MemberOfferRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalMemberEventStore implements MemberEventReader, MemberOfferRepository
{
    private const string SELECT = "SELECT o.id, o.event_id, e.title, e.description, o.starts_at, o.ends_at, o.time_zone, o.venue_name, o.venue_address, o.status,
            p.open AS policy_open, p.waitlist_enabled, p.capacity, e.active AS event_active, COALESCE(m.enabled, FALSE) AS offered,
            r.id AS r_id, r.status AS r_status, r.version AS r_version, a.status AS a_status, ci.image_id AS cover_image_id,
            (SELECT COUNT(*) FROM event_registrations c WHERE c.occurrence_id = o.id AND c.status = 'CONFIRMED') AS confirmed_count
        FROM event_occurrences o
        JOIN event_definitions e ON e.id = o.event_id
        JOIN event_registration_policies p ON p.occurrence_id = o.id
        LEFT JOIN event_member_offers m ON m.event_id = e.id
        LEFT JOIN event_cover_images ci ON ci.event_id = e.id
        LEFT JOIN event_registrations r ON r.occurrence_id = o.id AND r.person_id = :person
        LEFT JOIN event_attendance a ON a.registration_id = r.id";

    private const string PUBLISHED = "(COALESCE(m.enabled, FALSE) AND e.active AND o.status = 'SCHEDULED')";

    public function __construct(private Connection $connection)
    {
    }

    public function offer(string $eventId): MemberOffer
    {
        $row = $this->connection->fetchAssociative('SELECT enabled, version FROM event_member_offers WHERE event_id = ?', [$eventId]);

        return false === $row ? new MemberOffer(false, 0) : new MemberOffer((bool) $row['enabled'], (int) $row['version']);
    }

    public function save(string $eventId, bool $enabled, int $version): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_member_offers (event_id, enabled, version) VALUES (:event, :enabled, :version)
             ON CONFLICT (event_id) DO UPDATE SET enabled = EXCLUDED.enabled, version = EXCLUDED.version',
            ['event' => $eventId, 'enabled' => $enabled, 'version' => $version],
            ['enabled' => ParameterType::BOOLEAN],
        );
    }

    public function list(string $personId, DateTimeImmutable $from, DateTimeImmutable $until, bool $mine, int $limit, int $offset): array
    {
        $available = $mine ? 'r.id IS NOT NULL' : self::PUBLISHED;
        $rows = $this->connection->executeQuery(
            self::SELECT.' WHERE '.$available.' AND o.ends_at > :from AND o.starts_at < :until ORDER BY o.starts_at, o.id LIMIT :limit OFFSET :offset',
            ['person' => $personId, 'from' => Timestamps::toDatabase($from), 'until' => Timestamps::toDatabase($until), 'limit' => $limit, 'offset' => $offset],
        )->fetchAllAssociative();

        return array_map(self::item(...), $rows);
    }

    public function detail(string $personId, string $occurrenceId): ?MemberEventItem
    {
        $row = $this->connection->fetchAssociative(
            self::SELECT.' WHERE o.id = :id AND ('.self::PUBLISHED.' OR r.id IS NOT NULL)',
            ['person' => $personId, 'id' => $occurrenceId],
        );

        return false === $row ? null : self::item($row);
    }

    /** @param array<string, mixed> $row */
    private static function item(array $row): MemberEventItem
    {
        $capacity = null === $row['capacity'] ? null : (int) $row['capacity'];

        return new MemberEventItem(
            (string) $row['id'],
            (string) $row['event_id'],
            (string) $row['title'],
            (string) $row['description'],
            Timestamps::fromDatabase((string) $row['starts_at']),
            Timestamps::fromDatabase((string) $row['ends_at']),
            (string) $row['time_zone'],
            (string) $row['venue_name'],
            (string) $row['venue_address'],
            (string) $row['status'],
            (bool) $row['policy_open'] && (bool) $row['event_active'] && (bool) $row['offered'],
            (bool) $row['waitlist_enabled'],
            null === $capacity ? null : max(0, $capacity - (int) $row['confirmed_count']),
            null === $row['r_id'] ? null : new MemberBooking((string) $row['r_id'], (string) $row['r_status'], (int) $row['r_version'], null === $row['a_status'] ? null : (string) $row['a_status']),
            null === $row['cover_image_id'] ? null : (string) $row['cover_image_id'],
        );
    }
}
