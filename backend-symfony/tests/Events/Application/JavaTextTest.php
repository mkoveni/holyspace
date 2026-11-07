<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\VenueView;
use App\Events\Application\JavaText;
use App\Events\Application\ScheduleDraft;
use App\Events\Domain\EventSchedule;
use App\Events\Domain\TimeOfDay;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\TestCase;

/** The strings Java's String.valueOf gives the hashed record types; a preview hash depends on every character. */
final class JavaTextTest extends TestCase
{
    public function testDraftRendersLikeAJavaRecord(): void
    {
        $draft = new ScheduleDraft('00000000-0000-4000-8000-0000000000a1', 'Europe/London', 'WEEKLY', 2, 3, CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30:15'), 1);

        self::assertSame(
            'Draft[venueId=00000000-0000-4000-8000-0000000000a1, timeZone=Europe/London, frequency=WEEKLY, interval=2, count=3, firstDate=2026-07-05, startsAt=10:00, endsAt=11:30:15, endDayOffset=1]',
            JavaText::draft($draft),
        );
        self::assertNull(JavaText::draft(null));
    }

    public function testVenueAndWindowsRenderLikeJavaRecords(): void
    {
        $venue = new VenueView('00000000-0000-4000-8000-0000000000a1', 'Main hall', '1 Church St', true, true, 4);
        $window = EventSchedule::window(CalendarDate::parse('2026-07-05'), TimeOfDay::parse('10:00'), TimeOfDay::parse('11:30'), 0, 'Europe/London');

        self::assertSame('Venue[id=00000000-0000-4000-8000-0000000000a1, name=Main hall, address=1 Church St, exclusive=true, active=true, version=4]', JavaText::venue($venue));
        self::assertSame('[Window[originDate=2026-07-05, startsAt=2026-07-05T09:00:00Z, endsAt=2026-07-05T10:30:00Z]]', JavaText::windows([$window]));
        self::assertSame('[]', JavaText::windows([]));
    }
}
