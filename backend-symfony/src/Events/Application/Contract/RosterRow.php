<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

/** A registration with its attendance record, if any, and the derived roster state. */
final readonly class RosterRow
{
    public function __construct(
        public RegistrationView $registration,
        public ?AttendanceView $attendance,
        public string $state,
    ) {
    }
}
