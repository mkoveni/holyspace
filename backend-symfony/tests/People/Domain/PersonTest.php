<?php

declare(strict_types=1);

namespace App\Tests\People\Domain;

use App\People\Domain\Person;
use App\People\Domain\ValueObject\AgeCategory;
use App\People\Domain\ValueObject\Gender;
use App\People\Domain\ValueObject\LifeStatus;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PersonTest extends TestCase
{
    private const string ID = '11111111-1111-4111-8111-111111111111';

    private DateTimeImmutable $now;
    private CalendarDate $today;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-06-01T10:00:00Z');
        $this->today = CalendarDate::parse('2026-06-01');
    }

    public function testDetailsAreNormalisedAndDefaulted(): void
    {
        $details = new PersonDetails(' dr ', '  Ada ', ' Lovelace  ', null, null, null, null);

        self::assertSame('DR', $details->titleCode);
        self::assertSame('Ada', $details->firstName);
        self::assertSame('Lovelace', $details->lastName);
        self::assertSame(Gender::Unknown, $details->gender);
        self::assertSame(LifeStatus::Unknown, $details->lifeStatus);
        self::assertNull((new PersonDetails('  ', 'A', 'B', null, null, null, null))->titleCode);
    }

    public function testNamesAreRequiredAndBounded(): void
    {
        $this->expectInvalid(static fn (): PersonDetails => new PersonDetails(null, '  ', 'B', null, null, null, null));
        $this->expectInvalid(static fn (): PersonDetails => new PersonDetails(null, str_repeat('a', 101), 'B', null, null, null, null));
        $this->expectInvalid(static fn (): PersonDetails => new PersonDetails('1BAD', 'A', 'B', null, null, null, null));
    }

    public function testDeathRequiresDeceasedStatusAndFollowsBirth(): void
    {
        $this->expectInvalid(static fn (): PersonDetails => new PersonDetails(null, 'A', 'B', null, null, LifeStatus::Living, CalendarDate::parse('2020-01-01')));
        $this->expectInvalid(static fn (): PersonDetails => new PersonDetails(null, 'A', 'B', CalendarDate::parse('2000-01-01'), null, LifeStatus::Deceased, CalendarDate::parse('1999-12-31')));
        $details = new PersonDetails(null, 'A', 'B', CalendarDate::parse('2000-01-01'), null, LifeStatus::Deceased, CalendarDate::parse('2000-01-01'));

        self::assertSame('2000-01-01', $details->dateOfDeath?->iso());
    }

    public function testFutureDatesAreRejectedOnRegistration(): void
    {
        $future = new PersonDetails(null, 'A', 'B', CalendarDate::parse('2026-06-02'), null, null, null);

        $this->expectInvalid(fn (): Person => Person::register(self::ID, $future, null, $this->today, $this->now));
    }

    public function testRegistrationDefaultsToVisitorAtVersionZero(): void
    {
        $person = $this->person();

        self::assertSame(MembershipType::Visitor, $person->membershipType);
        self::assertSame(0, $person->revision->version);
        self::assertNull($person->archivedAt);
    }

    public function testChangesIncrementTheVersionAndCheckTheExpectedOne(): void
    {
        $person = $this->person();
        $later = $this->now->modify('+1 day');

        $updated = $person->update(new PersonDetails(null, 'Grace', 'Hopper', null, null, null, null), MembershipType::ActiveMember, 0, $this->today, $later);

        self::assertSame(1, $updated->revision->version);
        self::assertSame('Grace', $updated->details->firstName);
        self::assertEquals($this->now, $updated->revision->timeline->createdAt);
        self::assertEquals($later, $updated->revision->timeline->updatedAt);
        $this->expectConflict(fn (): Person => $person->touch(1, $later));
        $this->expectConflict(fn (): Person => $updated->touch(0, $later));
        $this->expectConflict(fn (): Person => $updated->touch(-1, $later));
    }

    public function testArchivingKeepsTheFirstArchiveTimeAndRestoreClearsIt(): void
    {
        $archived = $this->person()->archive(true, 0, $this->now->modify('+1 day'));
        $again = $archived->archive(true, 1, $this->now->modify('+2 days'));
        $restored = $again->archive(false, 2, $this->now->modify('+3 days'));

        self::assertEquals($this->now->modify('+1 day'), $again->archivedAt);
        self::assertNull($restored->archivedAt);
        self::assertSame(3, $restored->revision->version);
        $this->expectConflict($archived->requireAvailable(...));
    }

    public function testAgeCategoryUsesTheBirthDate(): void
    {
        $details = new PersonDetails(null, 'A', 'B', CalendarDate::parse('2010-06-02'), null, null, null);
        $person = Person::register(self::ID, $details, null, $this->today, $this->now);

        self::assertSame(AgeCategory::Child, $person->ageCategory($this->today));
        self::assertSame(AgeCategory::Adult, $person->ageCategory(CalendarDate::parse('2028-06-02')));
    }

    private function person(): Person
    {
        return Person::register(self::ID, new PersonDetails(null, 'Ada', 'Lovelace', null, null, null, null), null, $this->today, $this->now);
    }

    private function expectInvalid(callable $action): void
    {
        $this->expectKind(ErrorKind::Invalid, $action);
    }

    private function expectConflict(callable $action): void
    {
        $this->expectKind(ErrorKind::Conflict, $action);
    }

    private function expectKind(ErrorKind $kind, callable $action): void
    {
        try {
            $action();
        } catch (ContextException $error) {
            self::assertSame($kind, $error->kind());

            return;
        }
        self::fail('Expected a PeopleException.');
    }
}
