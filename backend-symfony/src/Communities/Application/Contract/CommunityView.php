<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class CommunityView
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $name,
        public string $description,
        public ?string $parentId,
        public string $campus,
        public bool $active,
        public int $version,
    ) {
    }
}
