<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class RelationshipView
{
    public function __construct(
        public string $id,
        public string $personId,
        public string $relatedPersonId,
        public string $relatedFirstName,
        public string $relatedLastName,
        public string $type,
        public ?string $endedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['person_id'],
            $row['related_person_id'],
            $row['related_first_name'],
            $row['related_last_name'],
            $row['type'],
            $row['ended_at'],
            $row['created_at'],
            $row['updated_at'],
        );
    }
}
