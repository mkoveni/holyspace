<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPerson;

use App\Shared\Domain\CalendarDate;

final readonly class GetPersonQuery
{
    public function __construct(
        public string $personId,
        public ?CalendarDate $asOf,
    ) {
    }
}
