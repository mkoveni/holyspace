<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\ReadModel;

use App\Events\Application\ReadModel\PublicEventDetails;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PublicEventDetails::class)]
final readonly class DbalPublicEventDetails implements PublicEventDetails
{
    public function __construct(private Connection $db)
    {
    }

    public function get(string $eventId, string $occurrenceId): ?array
    {
        $row = $this->db->fetchAssociative('SELECT e.title, e.description, e.timezone, o.starts_at, o.ends_at, o.location, o.status FROM church_events e INNER JOIN church_event_occurrences o ON o.event_id = e.id WHERE e.id = ? AND o.id = ?', [$eventId, $occurrenceId]);
        if (!$row || !$row['starts_at'] || !$row['ends_at']) {
            return null;
        }

        return ['title' => $row['title'], 'description' => (string) $row['description'], 'timezone' => $row['timezone'], 'startsAt' => (new DateTimeImmutable($row['starts_at'], new DateTimeZone('UTC')))->format(DATE_ATOM), 'endsAt' => (new DateTimeImmutable($row['ends_at'], new DateTimeZone('UTC')))->format(DATE_ATOM), 'location' => (string) $row['location'], 'status' => $row['status']];
    }
}
