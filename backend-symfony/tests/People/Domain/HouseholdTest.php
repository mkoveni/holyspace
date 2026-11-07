<?php

declare(strict_types=1);

namespace App\Tests\People\Domain;

use App\People\Domain\Household;
use App\People\Domain\HouseholdMembership;
use App\Shared\Domain\ContextException;
use App\Shared\Domain\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HouseholdTest extends TestCase
{
    private const string HOUSEHOLD = '11111111-1111-4111-8111-111111111111';
    private const string OTHER = '22222222-2222-4222-8222-222222222222';
    private const string PERSON = '33333333-3333-4333-8333-333333333333';
    private const string MEMBER = '44444444-4444-4444-8444-444444444444';

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-06-01T10:00:00Z');
    }

    public function testRenamingChecksTheVersionAndBumpsIt(): void
    {
        $household = Household::create(self::HOUSEHOLD, ' Mabasa family ', $this->now);

        $renamed = $household->update('Mabasa', 0, $this->now->modify('+1 hour'));

        self::assertSame('Mabasa family', $household->name);
        self::assertSame('Mabasa', $renamed->name);
        self::assertSame(1, $renamed->revision->version);
        $this->expectKind(ErrorKind::Conflict, fn (): Household => $renamed->update('Again', 0, $this->now));
        $this->expectKind(ErrorKind::Invalid, fn (): Household => $renamed->update('  ', 1, $this->now));
    }

    public function testPrimaryContactMustBeACurrentMemberOfThisHousehold(): void
    {
        $household = Household::create(self::HOUSEHOLD, 'Family', $this->now);
        $later = $this->now->modify('+1 day');
        $current = HouseholdMembership::start(self::MEMBER, self::HOUSEHOLD, self::PERSON, false, $this->now);
        $foreign = HouseholdMembership::start(self::MEMBER, self::OTHER, self::PERSON, false, $this->now);
        $ended = $current->end($later);

        self::assertSame(self::MEMBER, $household->contact($current, 0, $later)->primaryContactMembershipId);
        self::assertNull($household->contact($current, 0, $later)->contact(null, 1, $later)->primaryContactMembershipId);
        $this->expectKind(ErrorKind::Invalid, fn (): Household => $household->contact($foreign, 0, $later));
        $this->expectKind(ErrorKind::Invalid, fn (): Household => $household->contact($ended, 0, $later->modify('+1 day')));
    }

    public function testMembershipEndsOnceAndOnlyAfterItStarted(): void
    {
        $membership = HouseholdMembership::start(self::MEMBER, self::HOUSEHOLD, self::PERSON, true, $this->now);

        $ended = $membership->end($this->now->modify('+1 day'));

        self::assertEquals($this->now->modify('+1 day'), $ended->validTo);
        self::assertTrue($membership->currentAt($this->now));
        self::assertFalse($ended->currentAt($this->now->modify('+1 day')));
        $this->expectKind(ErrorKind::Conflict, fn (): HouseholdMembership => $ended->end($this->now->modify('+2 days')));
        $this->expectKind(ErrorKind::Conflict, fn (): HouseholdMembership => $membership->end($this->now));
        $this->expectKind(ErrorKind::Invalid, fn (): HouseholdMembership => HouseholdMembership::reconstitute(self::MEMBER, self::HOUSEHOLD, self::PERSON, false, $this->now, $this->now));
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
