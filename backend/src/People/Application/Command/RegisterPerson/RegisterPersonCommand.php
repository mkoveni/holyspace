<?php

declare(strict_types=1);

namespace App\People\Application\Command\RegisterPerson;

use App\Shared\Application\CQRS\Command;

final readonly class RegisterPersonCommand implements Command
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $dateOfBirth = null,
        public string $gender = 'unspecified',
        public ?string $email = null,
        public ?string $phone = null,
    ) {
    }
}
