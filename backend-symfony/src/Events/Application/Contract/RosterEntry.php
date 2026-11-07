<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class RosterEntry
{
    public function __construct(
        public RegistrationView $registration,
        public ?AttendanceView $attendance,
        public string $name,
        public string $status,
    ) {
    }
}
