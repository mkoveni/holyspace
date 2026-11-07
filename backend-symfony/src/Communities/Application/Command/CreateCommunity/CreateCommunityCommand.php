<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CreateCommunity;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class CreateCommunityCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $id,
        public string $kind,
        public string $name,
        public string $description,
        public ?string $parentId,
        public string $campus,
    ) {
    }
}
