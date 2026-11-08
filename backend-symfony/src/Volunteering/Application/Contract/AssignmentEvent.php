<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `RosterReader.Event`: one step of an assignment's history. */
final readonly class AssignmentEvent
{
    public function __construct(public string $id, public string $action, public string $reason, public DateTimeImmutable $occurredAt)
    {
    }
}
