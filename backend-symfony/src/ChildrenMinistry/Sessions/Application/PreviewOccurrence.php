<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Domain\RecurrenceSlot;

final readonly class PreviewOccurrence
{
    /**
     * @param list<string> $conflicts        block creation
     * @param list<string> $staffingWarnings allow planning
     */
    public function __construct(public RecurrenceSlot $slot, public array $conflicts, public array $staffingWarnings)
    {
    }
}
