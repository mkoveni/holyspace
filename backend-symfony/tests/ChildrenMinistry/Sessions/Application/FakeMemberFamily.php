<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Sessions\Application\Contract\MemberFamilyReader;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;

/** Authority held as a list of [guardian, child] pairs; the time filters of the real reader are not repeated. */
final class FakeMemberFamily implements MemberFamilyReader
{
    /** @param list<array{string, string}> $pairs */
    public function __construct(public array $pairs = [])
    {
    }

    public function children(string $guardianId, DateTimeImmutable $at, int $limit, int $offset): array
    {
        $children = array_values(array_unique(array_map(static fn (array $pair): string => $pair[1], array_filter($this->pairs, static fn (array $pair): bool => $pair[0] === $guardianId))));

        return \array_slice($children, $offset, $limit);
    }

    public function authorized(string $guardianId, string $childId, DateTimeImmutable $at): bool
    {
        return \in_array([$guardianId, $childId], $this->pairs, true);
    }

    public function programmes(string $childId, CalendarDate $on, int $limit, int $offset): array
    {
        return [];
    }

    public function sessions(string $childId, DateTimeImmutable $from, DateTimeImmutable $until, int $limit, int $offset): array
    {
        return [];
    }

    public function attendance(string $guardianId, string $childId, DateTimeImmutable $at, int $limit, int $offset): array
    {
        return [];
    }
}
