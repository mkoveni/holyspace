<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class FamilySummaryView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $memberCount,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['name'],
            (int) $row['member_count'],
            $row['created_at'],
            $row['updated_at'],
        );
    }
}
