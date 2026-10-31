<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class EducationView
{
    public function __construct(
        public string $id,
        public string $personId,
        public string $institution,
        public string $qualification,
        public ?string $startDate,
        public ?string $endDate,
        public ?string $fieldOfStudy,
        public ?string $notes,
        public string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['person_id'],
            $row['institution'],
            $row['qualification'],
            $row['start_date'],
            $row['end_date'],
            $row['field_of_study'],
            $row['notes'],
            $row['created_at'],
        );
    }
}
