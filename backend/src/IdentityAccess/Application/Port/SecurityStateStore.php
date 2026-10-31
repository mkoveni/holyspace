<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

use DateTimeImmutable;

interface SecurityStateStore extends SecurityStateReader
{
    public function find(string $userId): ?array;

    public function save(array $state): void;

    public function audit(string $userId, ?string $actorId, string $action, string $reference, DateTimeImmutable $at): void;
}
