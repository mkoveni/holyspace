<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AuditTimestamps;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class AuditTimestampsTest extends TestCase
{
    public function testInjectedClockMakesAuditTimestampsDeterministic(): void
    {
        $original = Clock::get();
        $clock = new MockClock('2030-01-01T09:00:00Z');
        Clock::set($clock);
        try {
            $audit = AuditTimestamps::now();
            $created = $audit->createdAt();
            $clock->sleep(60);
            $audit->touch();
            self::assertSame('2030-01-01T09:00:00+00:00', $created->format(DATE_ATOM));
            self::assertSame('2030-01-01T09:01:00+00:00', $audit->updatedAt()->format(DATE_ATOM));
            self::assertSame($created, $audit->createdAt());
            try {
                $audit->touch($created);
                self::fail('Audit history must not move backwards.');
            } catch (InvalidArgumentException) {
            }
            self::assertSame('2030-01-01T09:01:00+00:00', $audit->updatedAt()->format(DATE_ATOM));
        } finally {
            Clock::set($original);
        }
    }
}
