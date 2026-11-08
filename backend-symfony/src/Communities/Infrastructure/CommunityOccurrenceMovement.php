<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure;

use App\Events\Application\Contract\OccurrenceMovementPolicy;
use Doctrine\DBAL\Connection;

/** Java `CommunityOccurrenceMovement`: an occurrence whose group roster is prepared holds evidence and may not move. */
final readonly class CommunityOccurrenceMovement implements OccurrenceMovementPolicy
{
    public function __construct(private Connection $connection)
    {
    }

    public function movable(string $occurrenceId): bool
    {
        return false === $this->connection->createQueryBuilder()
            ->select('1')
            ->from('community_meetings')
            ->where('occurrence_id = :occurrence', 'roster_frozen')
            ->setParameter('occurrence', $occurrenceId)
            ->fetchOne();
    }
}
