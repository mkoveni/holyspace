<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

final readonly class ScheduledSession
{
    public function __construct(public string $id, public CalendarDate $date, public DateTimeImmutable $startsAt, public DateTimeImmutable $endsAt, public SessionStatus $status)
    {
    }
}
