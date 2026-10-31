<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Query\ListLifeEvents;

use App\Shared\Application\CQRS\Query;
use InvalidArgumentException;

final readonly class ListLifeEventsQuery implements Query
{
    public function __construct(
        public ?string $type = null,
        public ?int $month = null,
        public ?int $year = null,
        public bool $anniversaries = false,
    ) {
        if (null !== $month && ($month < 1 || $month > 12)) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }
        if (null !== $year && ($year < 1 || $year > 9999)) {
            throw new InvalidArgumentException('Year must be between 1 and 9999.');
        }
    }
}
