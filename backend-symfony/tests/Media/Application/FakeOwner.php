<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Contract\ImageOwnership;

/** An owning context's answers, set per test; every question is logged so call order can be asserted. */
final class FakeOwner implements ImageOwnership
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        private readonly string $type = 'event',
        public bool $manage = true,
        public bool $view = true,
        public bool $exists = true,
    ) {
    }

    public function ownerType(): string
    {
        return $this->type;
    }

    public function canView(string $actorId, string $ownerId): bool
    {
        $this->calls[] = 'canView';

        return $this->view;
    }

    public function canManage(string $actorId, string $ownerId): bool
    {
        $this->calls[] = 'canManage';

        return $this->manage;
    }

    public function exists(string $ownerId): bool
    {
        $this->calls[] = 'exists';

        return $this->exists;
    }
}
