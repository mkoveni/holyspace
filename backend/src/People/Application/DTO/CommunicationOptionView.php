<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class CommunicationOptionView
{
    public function __construct(
        public string $id,
        public string $personId,
        public string $channel,
        public string $value,
        public bool $preferred,
        public string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['person_id'],
            $row['channel'],
            $row['value'],
            (bool) $row['preferred'],
            $row['created_at'],
        );
    }
}
