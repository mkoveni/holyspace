<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

use App\Shared\Domain\CalendarDate;

final readonly class PersonDirectoryEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public ?CalendarDate $birthDate,
        public bool $available,
    ) {
    }
}
