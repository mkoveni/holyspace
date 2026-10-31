<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class FamilyMemberView
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $dateOfBirth,
        public string $gender,
        public ?string $email,
        public ?string $phone,
        public string $role,
        public string $membershipCreatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['date_of_birth'],
            $row['gender'],
            $row['email'],
            $row['phone'],
            $row['role'],
            $row['membership_created_at'],
        );
    }
}
