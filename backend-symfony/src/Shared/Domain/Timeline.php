<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * When an aggregate was created and last changed. Only the owning aggregate advances it, from the
 * instant its use case supplies, so handlers and mappers never set these dates independently.
 */
final readonly class Timeline
{
    private function __construct(public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt)
    {
        if ($updatedAt < $createdAt) {
            throw new DomainException('A record cannot be updated before it was created.');
        }
    }

    public static function start(DateTimeImmutable $now): self
    {
        return new self($now, $now);
    }

    public static function reconstitute(DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): self
    {
        return new self($createdAt, $updatedAt);
    }

    public function touch(DateTimeImmutable $now): self
    {
        return new self($this->createdAt, $now);
    }
}
