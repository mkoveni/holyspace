<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\Contract\ActivitySource;
use App\Volunteering\Application\Contract\SourceActivity;
use DateTimeImmutable;

final class FakeActivitySource implements ActivitySource
{
    /** @param array<string, SourceActivity> $activities */
    public function __construct(private readonly string $kind, public array $activities = [])
    {
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function find(string $id): ?SourceActivity
    {
        return $this->activities[$id] ?? null;
    }

    public function upcoming(DateTimeImmutable $from, DateTimeImmutable $until, ?int $limit = null, int $offset = 0): array
    {
        return \array_slice(array_values($this->activities), $offset, $limit);
    }
}
