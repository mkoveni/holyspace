<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\SaveChildSupport;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;
use SensitiveParameter;

/** @implements Command<null> */
final readonly class SaveChildSupportCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $childId,
        public int $expectedVersion,
        #[SensitiveParameter] public ?string $allergies,
        #[SensitiveParameter] public ?string $careInstructions,
        #[SensitiveParameter] public ?string $restrictedNotes,
        public CalendarDate $reviewOn,
        public CalendarDate $retainUntil,
    ) {
    }
}
