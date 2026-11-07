<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RevokeConsent;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RevokeConsentCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $id,
        public int $expectedVersion,
        public ?string $reason,
    ) {
    }
}
