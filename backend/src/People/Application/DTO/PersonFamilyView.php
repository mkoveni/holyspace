<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class PersonFamilyView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public string $membershipCreatedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['name'],
            $row['role'],
            $row['membership_created_at'],
            $row['created_at'],
            $row['updated_at'],
        );
    }
}
