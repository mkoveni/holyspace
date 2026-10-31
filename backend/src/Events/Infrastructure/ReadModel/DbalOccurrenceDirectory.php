<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\ReadModel;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Domain\ValueObject\Input;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OccurrenceDirectory::class)]
final readonly class DbalOccurrenceDirectory implements OccurrenceDirectory
{
    public function __construct(private Connection $db)
    {
    }

    public function get(string $id): ?array
    {
        return $this->getMany([$id])[$id] ?? null;
    }

    public function getMany(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        foreach ($ids as $id) {
            Input::id($id);
        }
        $occurrences = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = $this->db->fetchAllAssociative(
                'SELECT o.*, e.title, e.timezone FROM church_event_occurrences o INNER JOIN church_events e ON e.id = o.event_id WHERE o.id IN (?)',
                [$chunk], [ArrayParameterType::STRING],
            );
            foreach ($rows as $row) {
                $occurrences[$row['id']] = ['id' => $row['id'], 'eventId' => $row['event_id'], 'title' => $row['title'], 'startsAt' => (null === $row['starts_at'] ? null : (new DateTimeImmutable($row['starts_at']))->format(DATE_ATOM)), 'endsAt' => (null === $row['ends_at'] ? null : (new DateTimeImmutable($row['ends_at']))->format(DATE_ATOM)), 'status' => $row['status'], 'timezone' => $row['timezone'], 'location' => $row['location']];
            }
        }

        return $occurrences;
    }
}
