<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Participation\Domain;

use App\ChildrenMinistry\Participation\Domain\Location;
use App\ChildrenMinistry\Participation\Domain\MinistryClass;
use App\ChildrenMinistry\Participation\Domain\Programme;
use App\ChildrenMinistry\Participation\Domain\Room;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParticipationDomainTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    public function testProgrammesStartWithoutAPolicyAtVersionZero(): void
    {
        $programme = Programme::create(self::ID, ' Sunday children ', 2, 8, new DateTimeImmutable('2026-01-01T00:00:00Z'));

        self::assertSame('Sunday children', $programme->name);
        self::assertNull($programme->currentPolicyId);
        self::assertSame(0, $programme->version);
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function badProgrammes(): iterable
    {
        yield 'no staff' => ['A', 0, 5];
        yield 'no ratio' => ['A', 1, 0];
        yield 'too many staff' => ['A', 101, 5];
        yield 'ratio too large' => ['A', 1, 101];
        yield 'blank name' => ['  ', 1, 5];
        yield 'long name' => [str_repeat('x', 121), 1, 5];
    }

    #[DataProvider('badProgrammes')]
    public function testInvalidProgrammesAreRejected(string $name, int $staff, int $ratio): void
    {
        $this->expectException(ChildrenException::class);

        Programme::create(self::ID, $name, $staff, $ratio, new DateTimeImmutable());
    }

    /** @return iterable<string, array{int, int}> */
    public static function badRanges(): iterable
    {
        yield 'negative minimum' => [-1, 12];
        yield 'empty range' => [12, 12];
        yield 'inverted' => [24, 12];
        yield 'beyond eighteen years' => [0, 217];
    }

    #[DataProvider('badRanges')]
    public function testClassAgeRangesAreValidated(int $min, int $max): void
    {
        $this->expectException(ChildrenException::class);

        MinistryClass::create(self::ID, self::ID, 'Juniors', $min, $max);
    }

    public function testClassAgeBoundsAreInclusiveThenExclusive(): void
    {
        $group = MinistryClass::create(self::ID, self::ID, 'Juniors', 60, 120);
        $birthday = CalendarDate::parse('2016-10-26');

        $group->requireAge($birthday, CalendarDate::parse('2021-10-26'));
        $group->requireAge($birthday, CalendarDate::parse('2026-10-25'));
        $this->expectException(ChildrenException::class);
        $group->requireAge($birthday, CalendarDate::parse('2026-10-26'));
    }

    public function testChildrenOutsideTheRangeOrWithoutABirthDateAreRefused(): void
    {
        $group = MinistryClass::create(self::ID, self::ID, 'Juniors', 60, 120);
        $birthday = CalendarDate::parse('2016-10-26');

        foreach ([[$birthday, '2021-10-25'], [null, '2026-01-01'], [$birthday, '2016-01-01']] as [$birth, $on]) {
            try {
                $group->requireAge($birth, CalendarDate::parse($on));
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAdultsCannotBePlacedInAClass(): void
    {
        $group = MinistryClass::create(self::ID, self::ID, 'Everyone', 0, 216);

        $this->expectExceptionMessage('A recorded birth date identifying a child under 18 is required.');
        $group->requireAge(CalendarDate::parse('2000-01-01'), CalendarDate::parse('2026-01-01'));
    }

    public function testLocationsNeedARealTimeZone(): void
    {
        self::assertSame('Africa/Johannesburg', Location::create(self::ID, ' Main campus ', 'Africa/Johannesburg')->timeZone);

        foreach (['Mars/Olympus', '', 'not a zone'] as $zone) {
            try {
                Location::create(self::ID, 'Main', $zone);
                self::fail("Accepted $zone");
            } catch (ChildrenException $error) {
                self::assertSame('Select a valid IANA time zone.', $error->getMessage());
            }
        }
    }

    public function testRoomsNeedAPositiveCapacity(): void
    {
        self::assertSame(10, Room::create(self::ID, self::ID, 'Room A', 10)->capacity);

        $this->expectException(ChildrenException::class);
        Room::create(self::ID, self::ID, 'Room A', 0);
    }
}
