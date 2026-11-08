<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateVolunteerShift;

use App\Shared\Application\Command;
use App\Volunteering\Application\Contract\ShiftDraft;

/** @implements Command<string> */
final readonly class CreateVolunteerShiftCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?ShiftDraft $draft,
    ) {
    }
}
