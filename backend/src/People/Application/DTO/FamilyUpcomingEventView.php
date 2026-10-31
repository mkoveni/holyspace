<?php

declare(strict_types=1);

namespace App\People\Application\DTO;

final readonly class FamilyUpcomingEventView
{
    /**
     * @param list<string> $memberIds
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $eventDate,
        public ?string $notes,
        public string $status,
        public array $memberIds,
        public bool $isAnniversary = false,
        public ?string $originalEventDate = null,
    ) {
    }
}
