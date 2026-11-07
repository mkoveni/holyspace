<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\VerifyDonorLink;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class VerifyDonorLinkCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $accountId,
        public string $personId,
        public bool $active,
        public int $expectedVersion,
        public ?string $evidence,
    ) {
    }
}
