<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreateFund;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateFundCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $requestId,
        public ?string $name,
        public ?string $description,
    ) {
    }
}
