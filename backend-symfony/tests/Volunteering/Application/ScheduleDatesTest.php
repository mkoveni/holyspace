<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Application;

use App\Volunteering\Application\Contract\ShiftDraft;
use App\Volunteering\Application\Fingerprint;
use App\Volunteering\Application\ScheduleDates;
use App\Volunteering\Domain\VolunteerException;
use LogicException;
use PHPUnit\Framework\TestCase;

/** Port of Java `PreviewVolunteerScheduleHandlerTest`, plus Java's `UUID.nameUUIDFromBytes` derivation. */
final class ScheduleDatesTest extends TestCase
{
    private const string ID = '0b8f6a6e-58a1-4c1b-9d25-6a0f6fbd2b11';

    public function testPreviewsAreDeterministicOrderedAndExplicitAboutOvernightDuty(): void
    {
        $dates = ['2026-11-08', '2026-11-01'];
        $first = ScheduleDates::preview(self::ID, self::ID, self::ID, $dates, '22:00:00', '02:00:00', 'Africa/Johannesburg', 2);

        self::assertEquals($first, ScheduleDates::preview(self::ID, self::ID, self::ID, $dates, '22:00:00', '02:00:00', 'Africa/Johannesburg', 2));
        self::assertSame(['2026-11-01T20:00:00Z', '2026-11-08T20:00:00Z'], array_map(static fn (ShiftDraft $d): string => Fingerprint::instant($d->startsAt ?? throw new LogicException()), $first));
        self::assertSame('2026-11-02T00:00:00Z', Fingerprint::instant($first[0]->endsAt ?? throw new LogicException()));
        self::assertSame(ScheduleDates::nameUuid(self::ID.':2026-11-01'), $first[0]->requestId);
        self::assertSame(['STANDALONE', null, 2], [$first[0]->sourceKind, $first[0]->sourceId, $first[0]->positions]);
    }

    public function testRequestIdsMatchJavaNameUuidFromBytes(): void
    {
        self::assertSame('90015098-3cd2-3fb0-9696-3f7d28e17f72', ScheduleDates::nameUuid('abc'));
    }

    public function testRepeatedDatesAndAmbiguousLocalTimesAreRejected(): void
    {
        foreach ([
            [['2026-11-01', '2026-11-01'], '08:00:00', '09:00:00', 'UTC', 1],
            [['2026-11-01'], '01:30:00', '03:00:00', 'America/New_York', 1],
            [['2026-03-08'], '02:30:00', '04:00:00', 'America/New_York', 1],
            [[], '08:00:00', '09:00:00', 'UTC', 1],
            [['2026-11-01'], '08:00:00', '08:00:00', 'UTC', 1],
            [['2026-11-01'], '08:00:00', '09:00:00', 'UTC', 101],
            [['2026-11-01'], '08:00:00', '09:00:00', 'Mars/Base', 1],
        ] as [$dates, $start, $end, $zone, $positions]) {
            try {
                ScheduleDates::preview(self::ID, self::ID, self::ID, $dates, $start, $end, $zone, $positions);
                self::fail('Expected a refusal for '.json_encode([$dates, $start, $zone]));
            } catch (VolunteerException $refused) {
                self::assertNotSame('', $refused->getMessage());
            }
        }
    }
}
