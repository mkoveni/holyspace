<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class EmploymentView
{
    public function __construct(
        public string $id,
        public string $personId,
        public string $employer,
        public string $jobTitle,
        public ?string $startDate,
        public ?string $endDate,
        public ?string $industry,
        public ?string $workEmail,
        public ?string $workPhone,
        public ?string $notes,
        public string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['person_id'],
            $row['employer'],
            $row['job_title'],
            $row['start_date'],
            $row['end_date'],
            $row['industry'],
            $row['work_email'],
            $row['work_phone'],
            $row['notes'],
            $row['created_at'],
        );
    }
}
