<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use DateTimeImmutable;

final readonly class SessionDayPage
{
    /** @param list<SessionDayChild> $children */
    public function __construct(public array $children, public int $total, public DateTimeImmutable $checkedAt)
    {
    }
}
