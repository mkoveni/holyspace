<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateTitle;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class CreateTitleCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $code,
        public string $label,
    ) {
    }
}
