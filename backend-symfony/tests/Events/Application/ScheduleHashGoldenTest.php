<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Fingerprint;
use App\Events\Application\JavaText;
use App\Events\Application\ScheduleDraft;
use App\Events\Application\ScheduleReview;
use App\Events\Domain\TimeOfDay;
use App\Events\Domain\Venue;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Golden vectors for the hashes exchanged between a preview call and an apply call.
 *
 * The expected values were computed with an independent implementation (SHA-256 over a 4-byte big-endian length and
 * the UTF-8 bytes of each value, as java.security.MessageDigest does in Fingerprint.java) from the exact text Java's
 * record toString produces. They guard against drift in this port. They are NOT output captured from a running JVM:
 * before a mixed Java/Symfony deployment, replay one Java-created preview hash through this application (see the
 * porting log, E10).
 */
final class ScheduleHashGoldenTest extends TestCase
{
    private const string HALL = '00000000-0000-4000-8000-0000000000a1';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private function draft(): ScheduleDraft
    {
        return new ScheduleDraft(self::HALL, 'Europe/London', 'WEEKLY', 1, 2, CalendarDate::parse('2026-10-24'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30'), 0);
    }

    public function testThePreviewHashAcrossAnAutumnClockChange(): void
    {
        $venues = new InMemoryVenueRepository();
        $venues->insert(Venue::create(self::HALL, 'Main hall', '1 Church St', true));

        $preview = (new ScheduleReview($venues, new InMemoryOccurrenceStore(), new MockClock('2026-06-01 10:00:00 UTC')))->preview($this->draft());

        self::assertSame('e372ef7aefa6a25b71e7bd0d479a7cb48f6007cff100376e775a6e671a090828', $preview->hash);
        self::assertSame('2026-10-31T10:00:00Z', $preview->dates[1]->startsAt->format('Y-m-d\TH:i:s\Z'), 'the second date is after the clocks went back');
    }

    public function testTheTextBehindTheHash(): void
    {
        self::assertSame(
            'Draft[venueId=00000000-0000-4000-8000-0000000000a1, timeZone=Europe/London, frequency=WEEKLY, interval=1, count=2, firstDate=2026-10-24, startsAt=10:00, endsAt=11:30, endDayOffset=0]',
            JavaText::draft($this->draft()),
        );
    }

    public function testTheCreateRequestHashes(): void
    {
        $draft = JavaText::draft($this->draft());

        self::assertSame('95d2113285eb3985a6c60ee342fe8e4493aac9bb23d996be443f67060e8ea0b1', Fingerprint::of(self::ACTOR, 'Sunday service', 'SERVICE', '', $draft, 'abc'));
        self::assertSame('5afa90c72f31f0f5ec886b32857cf039eabdeec5372812c5243f849d753551b0', Fingerprint::of(self::ACTOR, '00000000-0000-4000-8000-0000000000e1', $draft, null), 'a missing preview hash hashes as the text "null"');
    }
}
