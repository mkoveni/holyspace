<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application;

use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Restricted: only the audited access use case may hand this to a caller. */
final readonly class SupportRecordView
{
    public function __construct(
        public string $childId,
        public string $allergies,
        public string $careInstructions,
        public string $restrictedNotes,
        public CalendarDate $reviewOn,
        public CalendarDate $retainUntil,
        public int $version,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['childId' => $this->childId, 'version' => $this->version];
    }
}
