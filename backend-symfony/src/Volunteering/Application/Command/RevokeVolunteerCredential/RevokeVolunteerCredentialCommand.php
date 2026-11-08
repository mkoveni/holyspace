<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\RevokeVolunteerCredential;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class RevokeVolunteerCredentialCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $volunteerId,
        public int $expectedVersion,
        public string $credentialId,
        public ?string $reason,
    ) {
    }
}
