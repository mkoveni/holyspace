<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CreateCommunityFollowUp;

use App\Shared\Application\Command;
use App\Shared\Domain\CalendarDate;

/** @implements Command<string> the follow-up id */
final readonly class CreateCommunityFollowUpCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $requestId,
        public string $communityId,
        public string $personId,
        public ?string $note,
        public ?CalendarDate $dueOn,
    ) {
    }
}
