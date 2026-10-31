<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Clock\Clock;

final class AuditTimestamps
{
    public function __construct(
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if ($updatedAt < $createdAt) {
            throw new InvalidArgumentException('Updated timestamp cannot be before created timestamp.');
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

    public static function now(?DateTimeImmutable $at = null): self
    {
        $now = $at ?? Clock::get()->now();

        return new self($now, $now);
    }

    public function touch(?DateTimeImmutable $at = null): void
    {
        $next = $at ?? Clock::get()->now();
        if ($next < $this->updatedAt) {
            throw new InvalidArgumentException('Audit timestamps cannot move backwards.');
        }
        $this->updatedAt = $next;
    }
}
