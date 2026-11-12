<?php

declare(strict_types=1);

namespace App\Events\Domain\Repository;

use App\Events\Domain\VisitorChild;
use DateTimeImmutable;

interface VisitorChildRepository
{
    public function insert(VisitorChild $child): void;

    /** @return array{status: string, registrationId: string, visitRemoved: bool}|null */
    public function state(string $childId): ?array;

    /** Settles a pending child; false when it was no longer pending. */
    public function settle(string $childId, string $status, string $actorId, DateTimeImmutable $at): bool;
}
