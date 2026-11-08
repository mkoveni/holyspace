<?php

declare(strict_types=1);

namespace App\Tests\Communities\Domain;

use App\Communities\Domain\CommunityException;
use App\Communities\Domain\FollowUp;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FollowUpTest extends TestCase
{
    public function testAFollowUpOpensWithAStrippedNoteAndCompletesOnce(): void
    {
        $followUp = FollowUp::open('f1', 'c1', 'p1', '  Call  ', CalendarDate::parse('2026-11-01'), 'a1', new DateTimeImmutable('2026-10-27T10:00:00Z'));

        self::assertSame(['Call', FollowUp::OPEN, 0], [$followUp->note, $followUp->status, $followUp->version]);
        $done = $followUp->complete();
        self::assertSame([FollowUp::DONE, 1, 'Call'], [$done->status, $done->version, $done->note]);
        $this->expectException(CommunityException::class);
        $this->expectExceptionMessage('This follow-up is already complete.');
        $done->complete();
    }

    public function testANoteIsRequired(): void
    {
        $this->expectExceptionMessage('Enter text of 1–2000 characters.');
        FollowUp::open('f1', 'c1', 'p1', ' ', CalendarDate::parse('2026-11-01'), 'a1', new DateTimeImmutable());
    }
}
