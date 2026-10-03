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

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public static function now(): self
    {
        $now = new DateTimeImmutable();
        return new self($now, $now);
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
