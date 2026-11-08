<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\UpdateCommunity;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class UpdateCommunityCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $communityId,
        public int $expectedVersion,
        public ?string $name,
        public ?string $description,
        public ?string $parentId,
        public ?string $campus,
        public bool $active,
        public ?string $reason,
    ) {
    }
}
