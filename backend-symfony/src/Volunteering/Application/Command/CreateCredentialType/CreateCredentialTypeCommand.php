<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\CreateCredentialType;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateCredentialTypeCommand implements Command
{
    public function __construct(
        public string $actorId,
        public ?string $code,
        public ?string $name,
    ) {
    }
}
