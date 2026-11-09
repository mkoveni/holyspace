<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use DateTimeImmutable;

/** A community the caller currently belongs to, as the member sees it; the role is MEMBER unless they lead it. */
final readonly class MemberCommunity
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $name,
        public string $description,
        public string $campus,
        public ?string $parentName,
        public DateTimeImmutable $joinedAt,
        public string $role,
        /** The community's cover picture's Media file id, served publicly by id (`/api/public/media/{id}`), or null. */
        public ?string $coverImageId,
    ) {
    }
}
