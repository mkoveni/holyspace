<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\BulkCreateVolunteerShifts;

use App\Shared\Application\Command;
use App\Volunteering\Application\Contract\ShiftDraft;

/** @implements Command<list<string>> */
final readonly class BulkCreateVolunteerShiftsCommand implements Command
{
    public function __construct(
        public string $actorId,
        /** @var list<ShiftDraft|null>|null */
        public ?array $drafts,
    ) {
    }
}
