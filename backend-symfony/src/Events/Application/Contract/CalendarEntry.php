<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** An occurrence with the title, type and archive state of its event. */
final readonly class CalendarEntry
{
    public function __construct(
        public OccurrenceView $occurrence,
        public string $title,
        public string $type,
        public bool $eventActive,
    ) {
    }
}
