<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\UpdateFund;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateFundCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $fundId,
        public int $expectedVersion,
        public ?string $name,
        public ?string $description,
        public bool $active,
        public ?string $reason,
    ) {
    }
}
