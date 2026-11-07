<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\VerifyMemberLink;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class VerifyMemberLinkCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $accountId,
        public string $personId,
        public bool $active,
        public int $expectedVersion,
        public string $evidence,
    ) {
    }
}
