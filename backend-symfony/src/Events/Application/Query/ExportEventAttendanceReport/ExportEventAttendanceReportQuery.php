<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ExportEventAttendanceReport;

use DateTimeImmutable;

final readonly class ExportEventAttendanceReportQuery
{
    public function __construct(public ?string $eventId, public string $search, public DateTimeImmutable $from, public DateTimeImmutable $until)
    {
    }
}
