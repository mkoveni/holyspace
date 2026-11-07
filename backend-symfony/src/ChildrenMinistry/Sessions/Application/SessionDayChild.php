<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

final readonly class SessionDayChild
{
    /** @param list<string> $issues */
    public function __construct(public string $childId, public string $name, public ?AttendanceView $attendance, public bool $ready, public array $issues)
    {
    }
}
