<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\IssueAcknowledgement;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class IssueAcknowledgementCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public string $giftId,
    ) {
    }
}
