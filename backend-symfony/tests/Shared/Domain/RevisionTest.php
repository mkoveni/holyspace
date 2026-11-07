<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Revision;
use App\Shared\Domain\StaleRevisionException;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RevisionTest extends TestCase
{
    public function testStartsAtVersionZeroWithMatchingTimes(): void
    {
        $now = new DateTimeImmutable('2026-01-01T10:00:00Z');

        $revision = Revision::start($now);

        self::assertSame(0, $revision->version);
        self::assertEquals($now, $revision->timeline->createdAt);
        self::assertEquals($now, $revision->timeline->updatedAt);
    }

    public function testNextAdvancesTheVersionAndOnlyTheUpdateTime(): void
    {
        $created = new DateTimeImmutable('2026-01-01T10:00:00Z');
        $later = new DateTimeImmutable('2026-01-02T10:00:00Z');

        $next = Revision::start($created)->next(0, $later);

        self::assertSame(1, $next->version);
        self::assertSame(0, $next->previousVersion());
        self::assertEquals($created, $next->timeline->createdAt);
        self::assertEquals($later, $next->timeline->updatedAt);
    }

    public function testStaleOrNegativeExpectationsAreRejected(): void
    {
        $revision = Revision::reconstitute(2, new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-01-01T00:00:00Z'));

        foreach ([0, 1, 3, -1] as $expected) {
            try {
                $revision->next($expected, new DateTimeImmutable('2026-01-02T00:00:00Z'));
                self::fail("Accepted expected version $expected");
            } catch (StaleRevisionException $error) {
                self::assertSame('The record has changed. Reload and try again.', $error->getMessage());
            }
        }
    }

    public function testATimelineCannotRunBackwards(): void
    {
        $this->expectException(DomainException::class);

        Revision::reconstitute(0, new DateTimeImmutable('2026-01-02T00:00:00Z'), new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }
}
