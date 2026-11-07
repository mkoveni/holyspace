<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Domain;

use App\ChildrenMinistry\Safeguarding\Domain\SupportRecord;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SupportRecordTest extends TestCase
{
    private const string CHILD = '11111111-1111-4111-8111-111111111111';

    public function testRevisingChecksTheVersionAndAdvancesIt(): void
    {
        $record = $this->record();

        $next = $record->revise(0, 'None', null, null, CalendarDate::parse('2026-08-01'), CalendarDate::parse('2027-08-01'), new DateTimeImmutable('2026-06-02T00:00:00Z'));

        self::assertSame(1, $next->version);
        self::assertSame('None', $next->allergies);
        $this->expectExceptionMessage('This record changed. Reload before trying again.');
        $record->revise(2, null, null, null, CalendarDate::parse('2026-08-01'), CalendarDate::parse('2027-08-01'), new DateTimeImmutable());
    }

    public function testRetentionMayEqualButNotPrecedeReview(): void
    {
        $same = SupportRecord::create(self::CHILD, null, null, null, CalendarDate::parse('2026-08-01'), CalendarDate::parse('2026-08-01'), new DateTimeImmutable());
        self::assertFalse($same->expiredOn(CalendarDate::parse('2026-08-01')), 'retained through the retention day');
        self::assertTrue($same->expiredOn(CalendarDate::parse('2026-08-02')));
        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('Retention date must not precede review date.');
        SupportRecord::create(self::CHILD, null, null, null, CalendarDate::parse('2026-08-02'), CalendarDate::parse('2026-08-01'), new DateTimeImmutable());
    }

    public function testTextIsTrimmedBoundedAndOptional(): void
    {
        $record = SupportRecord::create(self::CHILD, '  Peanuts ', null, '', CalendarDate::parse('2026-08-01'), CalendarDate::parse('2026-08-01'), new DateTimeImmutable());
        self::assertSame(['Peanuts', '', ''], [$record->allergies, $record->careInstructions, $record->restrictedNotes]);
        $this->expectException(ChildrenException::class);
        SupportRecord::create(self::CHILD, str_repeat('x', 2001), null, null, CalendarDate::parse('2026-08-01'), CalendarDate::parse('2026-08-01'), new DateTimeImmutable());
    }

    public function testTheCarePayloadNeverAppearsInDumps(): void
    {
        $record = SupportRecord::create(self::CHILD, 'Peanuts', 'Epipen in bag', 'Court order', CalendarDate::parse('2026-08-01'), CalendarDate::parse('2026-08-01'), new DateTimeImmutable());

        ob_start();
        var_dump($record);
        $dump = (string) ob_get_clean();

        foreach (['Peanuts', 'Epipen', 'Court order'] as $secret) {
            self::assertStringNotContainsString($secret, $dump);
        }
        self::assertStringContainsString(self::CHILD, $dump);
    }

    private function record(): SupportRecord
    {
        return SupportRecord::create(self::CHILD, 'Peanuts', 'Epipen', 'Notes', CalendarDate::parse('2026-08-01'), CalendarDate::parse('2027-08-01'), new DateTimeImmutable('2026-06-01T00:00:00Z'));
    }
}
