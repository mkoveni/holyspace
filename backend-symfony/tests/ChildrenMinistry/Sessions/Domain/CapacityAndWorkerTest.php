<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Domain;

use App\ChildrenMinistry\Sessions\Domain\CapacityPolicy;
use App\ChildrenMinistry\Sessions\Domain\Worker;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CapacityAndWorkerTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, int, bool, int, int}> expected, seats, room, eligible, leader, min staff, per staff */
    public static function capacities(): iterable
    {
        yield 'limited by seats' => [10, 10, 30, 5, true, 1, 5];
        yield 'limited by room' => [8, 20, 8, 5, true, 1, 5];
        yield 'limited by ratio' => [6, 20, 30, 3, true, 1, 2];
        yield 'below minimum staff' => [0, 20, 30, 1, true, 2, 5];
        yield 'no leader' => [0, 20, 30, 4, false, 1, 5];
        yield 'no staff' => [0, 20, 30, 0, true, 0, 5];
    }

    #[DataProvider('capacities')]
    public function testCapacityIsTheTightestOfSeatsRoomAndRatio(int $expected, int $seats, int $room, int $eligible, bool $leader, int $minimum, int $perStaff): void
    {
        self::assertSame($expected, CapacityPolicy::capacity($seats, $room, $eligible, $leader, $minimum, $perStaff));
    }

    private function worker(?string $revoked = null): Worker
    {
        return Worker::reconstitute('p', 'a', new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-12-31T00:00:00Z'), new DateTimeImmutable('2026-09-30T00:00:00Z'), 'Police check', 'v', null === $revoked ? null : new DateTimeImmutable($revoked), 0);
    }

    public function testAWorkerIsEligibleWhileScreeningAndTrainingCoverTheSession(): void
    {
        $now = new DateTimeImmutable('2026-06-01T00:00:00Z');

        self::assertTrue($this->worker()->eligible($now, new DateTimeImmutable('2026-09-30T00:00:00Z')));
        self::assertFalse($this->worker()->eligible($now, new DateTimeImmutable('2026-09-30T00:00:01Z')), 'training lapses first');
        self::assertFalse($this->worker()->eligible(new DateTimeImmutable('2025-12-31T23:59:59Z'), new DateTimeImmutable('2026-02-01T00:00:00Z')), 'not yet verified');
        self::assertFalse($this->worker('2026-05-01T00:00:00Z')->eligible($now, new DateTimeImmutable('2026-06-02T00:00:00Z')), 'revoked');
    }

    public function testClearanceMustEndAfterItStarts(): void
    {
        $this->expectException(ChildrenException::class);

        Worker::reconstitute('p', 'a', new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-09-30T00:00:00Z'), 'Check', 'v', null, 0);
    }
}
