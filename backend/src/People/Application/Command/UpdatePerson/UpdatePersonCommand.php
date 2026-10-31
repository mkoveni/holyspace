<?php

declare(strict_types=1);

namespace App\People\Application\Command\UpdatePerson;

use App\Shared\Application\CQRS\Command;

final readonly class UpdatePersonCommand implements Command
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $dateOfBirth,
        public string $gender,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
