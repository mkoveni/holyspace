<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\SetOnlineFund;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class SetOnlineFundCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $fundId,
        public bool $enabled,
        public int $expectedVersion,
    ) {
    }
}
