<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use DateTimeImmutable;

final class AuditTimestamps
{
    public function __construct(
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if ($updatedAt < $createdAt) {
            throw new \InvalidArgumentException('Updated timestamp cannot be before created timestamp.');
        }
    }

    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function touch(DateTimeImmutable $now): void
    {
        if ($now < $this->updatedAt) {
            throw new \InvalidArgumentException('Updated timestamp cannot move backwards.');
        }
        $this->updatedAt = $now;
    }
}
