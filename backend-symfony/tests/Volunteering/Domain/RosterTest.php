<?php

declare(strict_types=1);

namespace App\Tests\Volunteering\Domain;

use App\Volunteering\Domain\Assignment;
use App\Volunteering\Domain\CredentialType;
use App\Volunteering\Domain\Invitation;
use App\Volunteering\Domain\Shift;
use App\Volunteering\Domain\Volunteer;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RosterTest extends TestCase
{
    private static function shift(string $from = '2026-11-08T08:00:00Z', string $until = '2026-11-08T10:00:00Z', int $positions = 2, string $kind = 'STANDALONE', ?string $source = null): Shift
    {
        return Shift::plan('s', 'r', 'h', 't', 'role', $kind, $source, new DateTimeImmutable($from), new DateTimeImmutable($until), $positions);
    }

    public function testShiftsLastAtMostSevenDaysWithOneToAHundredPositions(): void
    {
        self::assertSame(0, self::shift('2026-11-01T08:00:00Z', '2026-11-08T08:00:00Z')->version);
        foreach ([
            static fn (): Shift => self::shift('2026-11-01T08:00:00Z', '2026-11-08T08:00:01Z'),
            static fn (): Shift => self::shift(positions: 0),
            static fn (): Shift => self::shift(positions: 101),
            static fn (): Shift => self::shift(kind: 'EVENT_OCCURRENCE'),
            static fn (): Shift => self::shift(source: 'x'),
            static fn (): Shift => self::shift('2026-11-08T10:00:00Z', '2026-11-08T10:00:00Z'),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Expected an invalid shift.');
            } catch (VolunteerException $refused) {
                self::assertNotSame('', $refused->getMessage());
            }
        }
    }

    public function testBookingsMoveOnlyTheVersionAndChangesMoveTheScheduleRevision(): void
    {
        $shift = self::shift();
        $touched = $shift->touch();
        self::assertSame([1, 0], [$touched->version, $touched->scheduleRevision]);
        $changed = $touched->change(new DateTimeImmutable('2026-11-08T09:00:00Z'), new DateTimeImmutable('2026-11-08T11:00:00Z'), 3, 'OPEN');
        self::assertSame([2, 1, 3], [$changed->version, $changed->scheduleRevision, $changed->positions]);
        self::assertSame('rev', $changed->withSourceRevision('rev')->sourceRevision);
        self::assertSame(2, $changed->withSourceRevision('rev')->version);
        $cancelled = $changed->change($changed->startsAt, $changed->endsAt, 3, 'CANCELLED');
        $this->expectExceptionMessage('Cancelled shifts cannot reopen.');
        $cancelled->change($changed->startsAt, $changed->endsAt, 3, 'OPEN');
    }

    public function testACancelledAssignmentNeverReopens(): void
    {
        $assignment = Assignment::book('a', 'r', 'h', 's', 'v', 'PROPOSED', 'actor', new DateTimeImmutable());
        $confirmed = $assignment->change('CONFIRMED');
        self::assertSame(['CONFIRMED', 1], [$confirmed->status, $confirmed->version]);
        $this->expectExceptionMessage('A cancelled assignment cannot reopen.');
        $confirmed->change('CANCELLED')->change('PROPOSED');
    }

    public function testInvitationsAreAnsweredOnce(): void
    {
        $at = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $invitation = Invitation::issue('i', 'a', 0, 0, $at);
        $accepted = $invitation->respond('ACCEPTED', $at);
        self::assertSame(['ACCEPTED', 1, $at], [$accepted->status, $accepted->version, $accepted->respondedAt]);
        try {
            $invitation->respond('PENDING', $at);
            self::fail('PENDING is not an answer.');
        } catch (VolunteerException $refused) {
            self::assertSame('This invitation is no longer pending.', $refused->getMessage());
        }
        $this->expectExceptionMessage('This invitation is no longer pending.');
        $accepted->respond('DECLINED', $at);
    }

    public function testVolunteerChangesMoveTheVersionAndAvailabilityItsRevision(): void
    {
        $at = new DateTimeImmutable('2026-11-08T08:00:00Z');
        $volunteer = Volunteer::register('v', 'p', null, $at);
        self::assertSame(['ACTIVE', 0, 0], [$volunteer->status, $volunteer->version, $volunteer->availabilityRevision]);
        $later = $at->modify('+1 hour');
        $changed = $volunteer->availability($later)->link('acc', $later)->touch($later);
        self::assertSame([3, 1, 'acc', $later, $at], [$changed->version, $changed->availabilityRevision, $changed->accountId, $changed->updatedAt, $changed->createdAt]);
        $this->expectExceptionMessage('Choose active, paused or archived.');
        $volunteer->change('RETIRED', $later);
    }

    public function testCredentialCodesAreLowercase(): void
    {
        self::assertSame('first-aid.v2', CredentialType::create('c', ' first-aid.v2 ', 'First aid')->code);
        $this->expectExceptionMessage('Use a lowercase credential code.');
        CredentialType::create('c', 'First', 'First aid');
    }
}
