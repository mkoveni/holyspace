<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

use DateTimeImmutable;

/** Java `RosterPlanning.Draft`: a shift to create. Fields may be missing; planning reports which rule fails. */
final readonly class ShiftDraft
{
    public function __construct(
        public ?string $requestId,
        public ?string $teamId,
        public ?string $roleId,
        public ?string $sourceKind,
        public ?string $sourceId,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public int $positions,
    ) {
    }
}
