<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\TicketCatalog;
use App\Events\Domain\Repository\TicketRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalTicketCatalog implements TicketCatalog
{
    private const string SELLABLE = "s.enabled AND (s.sales_open_at IS NULL OR s.sales_open_at <= :now) AND (s.sales_close_at IS NULL OR :now < s.sales_close_at)
        AND o.status = 'SCHEDULED' AND d.active AND o.ends_at > :now";

    public function __construct(private Connection $connection, private TicketRepository $tickets)
    {
    }

    public function onSale(DateTimeImmutable $now): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id, d.title, o.starts_at, o.ends_at, o.time_zone, o.venue_name, p.capacity,
                    (SELECT m.file_id FROM media_slots m WHERE m.owner_type = \'event\' AND m.owner_id = d.id AND m.kind = \'event\') AS cover_id, (SELECT m.file_id FROM media_slots m WHERE m.owner_type = \'event\' AND m.owner_id = d.id AND m.kind = \'event_banner\') AS banner_id,
                    (SELECT min(t.price_minor) FROM event_ticket_types t WHERE t.occurrence_id = o.id AND t.active) AS from_price
             FROM event_public_sales s
             JOIN event_occurrences o ON o.id = s.occurrence_id
             JOIN event_definitions d ON d.id = o.event_id
             LEFT JOIN event_registration_policies p ON p.occurrence_id = o.id
             WHERE '.self::SELLABLE.' AND EXISTS (SELECT 1 FROM event_ticket_types t WHERE t.occurrence_id = o.id AND t.active)
             ORDER BY o.starts_at, o.id LIMIT 100',
            ['now' => Timestamps::toDatabase($now)],
        );
        $events = [];
        foreach ($rows as $r) {
            $events[] = [
                'occurrenceId' => (string) $r['id'],
                'title' => (string) $r['title'],
                'startsAt' => Timestamps::fromDatabase((string) $r['starts_at']),
                'endsAt' => Timestamps::fromDatabase((string) $r['ends_at']),
                'timeZone' => (string) $r['time_zone'],
                'venueName' => (string) $r['venue_name'],
                'coverImageId' => null === $r['cover_id'] ? null : (string) $r['cover_id'],
                'bannerImageId' => null === $r['banner_id'] ? null : (string) $r['banner_id'],
                'fromPriceMinor' => null === $r['from_price'] ? null : (int) $r['from_price'],
                'available' => $this->hasRoom((string) $r['id'], null === $r['capacity'] ? null : (int) $r['capacity'], $now),
            ];
        }

        return $events;
    }

    public function detail(string $occurrenceId, DateTimeImmutable $now): ?array
    {
        $r = $this->connection->fetchAssociative(
            'SELECT o.id, d.title, d.description, o.starts_at, o.ends_at, o.time_zone, o.venue_name, o.venue_address, p.capacity, s.max_per_order,
                    (SELECT m.file_id FROM media_slots m WHERE m.owner_type = \'event\' AND m.owner_id = d.id AND m.kind = \'event\') AS cover_id, (SELECT m.file_id FROM media_slots m WHERE m.owner_type = \'event\' AND m.owner_id = d.id AND m.kind = \'event_banner\') AS banner_id
             FROM event_public_sales s
             JOIN event_occurrences o ON o.id = s.occurrence_id
             JOIN event_definitions d ON d.id = o.event_id
             LEFT JOIN event_registration_policies p ON p.occurrence_id = o.id
             WHERE o.id = :id AND '.self::SELLABLE,
            ['id' => $occurrenceId, 'now' => Timestamps::toDatabase($now)],
        );
        if (false === $r) {
            return null;
        }
        $types = [];
        foreach ($this->tickets->types($occurrenceId) as $type) {
            if ($type->active) {
                $types[] = [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'priceMinor' => $type->priceMinor,
                    'available' => null === $type->quantityCap || $this->tickets->typeTaken($type->id, $now) < $type->quantityCap,
                ];
            }
        }

        return [
            'occurrenceId' => (string) $r['id'],
            'title' => (string) $r['title'],
            'description' => (string) $r['description'],
            'startsAt' => Timestamps::fromDatabase((string) $r['starts_at']),
            'endsAt' => Timestamps::fromDatabase((string) $r['ends_at']),
            'timeZone' => (string) $r['time_zone'],
            'venueName' => (string) $r['venue_name'],
            'venueAddress' => (string) $r['venue_address'],
            'coverImageId' => null === $r['cover_id'] ? null : (string) $r['cover_id'],
            'bannerImageId' => null === $r['banner_id'] ? null : (string) $r['banner_id'],
            'maxPerOrder' => (int) $r['max_per_order'],
            'available' => $this->hasRoom($occurrenceId, null === $r['capacity'] ? null : (int) $r['capacity'], $now),
            'types' => $types,
        ];
    }

    public function event(string $occurrenceId): ?array
    {
        $r = $this->connection->fetchAssociative(
            'SELECT d.title, o.starts_at, o.ends_at, o.time_zone, o.venue_name, o.venue_address FROM event_occurrences o JOIN event_definitions d ON d.id = o.event_id WHERE o.id = ?',
            [$occurrenceId],
        );

        return false === $r ? null : [
            'title' => (string) $r['title'],
            'startsAt' => Timestamps::fromDatabase((string) $r['starts_at']),
            'endsAt' => Timestamps::fromDatabase((string) $r['ends_at']),
            'timeZone' => (string) $r['time_zone'],
            'venueName' => (string) $r['venue_name'],
            'venueAddress' => (string) $r['venue_address'],
        ];
    }

    public function orders(string $occurrenceId, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id, o.status, o.buyer_name, o.buyer_email_enc, o.total_minor, o.created_at,
                    (SELECT COALESCE(sum(l.quantity), 0) FROM event_ticket_order_lines l WHERE l.order_id = o.id) AS seats
             FROM event_ticket_orders o WHERE o.occurrence_id = ? ORDER BY o.created_at DESC, o.id LIMIT '.max(1, min(500, $limit)),
            [$occurrenceId],
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'status' => (string) $r['status'],
            'buyerName' => (string) $r['buyer_name'],
            'buyerEmailEncrypted' => (string) $r['buyer_email_enc'],
            'seats' => (int) $r['seats'],
            'totalMinor' => (int) $r['total_minor'],
            'createdAt' => Timestamps::fromDatabase((string) $r['created_at']),
        ], $rows);
    }

    private function hasRoom(string $occurrenceId, ?int $capacity, DateTimeImmutable $now): bool
    {
        return null === $capacity || $this->tickets->seatsTaken($occurrenceId, $now) < $capacity;
    }
}
