<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Infrastructure\Persistence;

use App\ChildrenMinistry\Reporting\Application\Contract\MinistryOverviewReader;
use App\ChildrenMinistry\Reporting\Application\OverviewSession;
use App\ChildrenMinistry\Reporting\Application\UpcomingSessions;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalMinistryOverviewStore implements MinistryOverviewReader
{
    private const string WHERE = "s.starts_at > :from AND s.starts_at < :until AND s.status NOT IN ('CLOSED', 'CANCELLED')";

    public function __construct(private Connection $connection)
    {
    }

    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until): UpcomingSessions
    {
        $parameters = ['from' => Timestamps::toDatabase($from), 'until' => Timestamps::toDatabase($until)];
        $total = (int) $this->connection->fetchOne('SELECT count(*) FROM children_sessions s WHERE '.self::WHERE, $parameters);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.id, p.name AS programme, c.name AS class_name, r.name AS room, s.starts_at, s.status
             FROM children_sessions s JOIN children_classes c ON c.id = s.class_id JOIN children_programmes p ON p.id = c.programme_id JOIN children_rooms r ON r.id = s.room_id
             WHERE '.self::WHERE.' ORDER BY s.starts_at, s.id LIMIT 5',
            $parameters,
        );

        return new UpcomingSessions($total, array_map(static fn (array $row): OverviewSession => new OverviewSession((string) $row['id'], (string) $row['programme'], (string) $row['class_name'], (string) $row['room'], Timestamps::fromDatabase((string) $row['starts_at']), (string) $row['status']), $rows));
    }
}
