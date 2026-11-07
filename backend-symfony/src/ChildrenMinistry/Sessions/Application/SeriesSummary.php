<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\Shared\Domain\CalendarDate;

final readonly class SeriesSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sourceSessionId,
        public string $className,
        public string $roomName,
        public string $timeZone,
        public CalendarDate $firstDate,
        public int $intervalWeeks,
        public int $occurrences,
        public int $totalSessions,
        public int $plannedSessions,
        public int $cancelledSessions,
        public bool $stopped,
        public int $version,
    ) {
    }
}
