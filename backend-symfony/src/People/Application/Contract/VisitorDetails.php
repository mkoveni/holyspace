<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

/** What a visitor typed about themselves. At least one of mobile or email is required by the registrar. */
final readonly class VisitorDetails
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $mobile,
        public ?string $email,
    ) {
    }
}
