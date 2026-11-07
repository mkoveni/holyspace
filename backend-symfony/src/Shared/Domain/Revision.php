<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;
use DomainException;

/** An aggregate's optimistic-concurrency version together with its Timeline. Every change goes through next(). */
final readonly class Revision
{
    private function __construct(public int $version, public Timeline $timeline)
    {
        if ($version < 0) {
            throw new DomainException('A revision cannot be negative.');
        }
    }

    public static function start(DateTimeImmutable $now): self
    {
        return new self(0, Timeline::start($now));
    }

    public static function reconstitute(int $version, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): self
    {
        return new self($version, Timeline::reconstitute($createdAt, $updatedAt));
    }

    /** @throws StaleRevisionException when the caller did not see the current version */
    public function next(int $expectedVersion, DateTimeImmutable $now): self
    {
        if ($expectedVersion < 0 || $expectedVersion !== $this->version) {
            throw new StaleRevisionException();
        }

        return new self($this->version + 1, $this->timeline->touch($now));
    }

    /** Advances without an optimistic check, for callers (such as older API clients) that cannot supply a version. */
    public function advance(DateTimeImmutable $now): self
    {
        return new self($this->version + 1, $this->timeline->touch($now));
    }

    /** The version the stored row must still have for this revision to be saved over it. */
    public function previousVersion(): int
    {
        return $this->version - 1;
    }
}
