<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class PersonAddressView
{
    public function __construct(
        public string $id,
        public string $type,
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $province,
        public ?string $postalCode,
        public string $country,
        public string $personId,
        public string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['type'],
            $row['line1'],
            $row['line2'],
            $row['city'],
            $row['province'],
            $row['postal_code'],
            $row['country'],
            $row['person_id'],
            $row['created_at'],
        );
    }
}
