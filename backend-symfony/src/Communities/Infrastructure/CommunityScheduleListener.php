<?php

declare(strict_types=1);

namespace App\Communities\Infrastructure;

use App\Events\Application\Contract\OccurrenceScheduleListener;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Java `CommunityScheduleListener`: keeps a linked meeting's `scheduled_at` in step with its occurrence, inside the
 * Events scheduling transaction. The meeting version does not move, as in Java.
 */
final readonly class CommunityScheduleListener implements OccurrenceScheduleListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function changed(string $occurrenceId, DateTimeImmutable $startsAt): void
    {
        $this->connection->createQueryBuilder()
            ->update('community_meetings')
            ->set('scheduled_at', ':at')
            ->where('occurrence_id = :occurrence')
            ->setParameter('at', Timestamps::toDatabase($startsAt))
            ->setParameter('occurrence', $occurrenceId)
            ->executeStatement();
    }
}
