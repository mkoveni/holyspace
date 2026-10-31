<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class PersonSearchItem
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $dateOfBirth,
        public ?string $email,
        public ?string $phone,
    ) {
    }
}
