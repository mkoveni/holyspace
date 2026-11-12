<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Persistence;

use App\Events\Application\Contract\VisitLinkReader;
use App\Events\Domain\Repository\VisitLinkRepository;
use App\Events\Domain\VisitLink;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalVisitLinkStore implements VisitLinkRepository, VisitLinkReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function findUnlocked(string $occurrenceId): ?VisitLink
    {
        $state = $this->state($occurrenceId);

        return null === $state ? null : VisitLink::reconstitute($occurrenceId, $state['enabled'], $state['generation']);
    }

    public function state(string $occurrenceId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT enabled, generation FROM event_visit_links WHERE occurrence_id = ?', [$occurrenceId]);

        return false === $row ? null : ['enabled' => (bool) $row['enabled'], 'generation' => (int) $row['generation']];
    }

    public function save(VisitLink $link, string $actorId, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO event_visit_links (occurrence_id, enabled, generation, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (occurrence_id) DO UPDATE SET enabled = EXCLUDED.enabled, generation = EXCLUDED.generation,
             updated_by = EXCLUDED.updated_by, updated_at = EXCLUDED.updated_at',
            [$link->occurrenceId, $link->enabled, $link->generation, $actorId, Timestamps::toDatabase($at)],
            [1 => ParameterType::BOOLEAN],
        );
    }
}
