<?php

declare(strict_types=1);

namespace App\Tests\Communities\Domain;

use App\Communities\Domain\CommunityException;
use App\Communities\Domain\GroupMeeting;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GroupMeetingTest extends TestCase
{
    private function meeting(): GroupMeeting
    {
        return GroupMeeting::schedule('m', 'c', 'o', new DateTimeImmutable('2026-07-05 10:00:00 UTC'));
    }

    public function testAScheduledMeetingIsOpenUnfrozenAtVersionZero(): void
    {
        $meeting = $this->meeting();

        self::assertSame([GroupMeeting::OPEN, false, 0], [$meeting->status, $meeting->rosterFrozen, $meeting->version]);
        self::assertTrue($meeting->isOpen());
    }

    public function testFreezingOnceMovesTheVersionAndAgainChangesNothing(): void
    {
        $frozen = $this->meeting()->freeze();

        self::assertSame([true, 1], [$frozen->rosterFrozen, $frozen->version]);
        self::assertSame($frozen, $frozen->freeze());
    }

    public function testGuestsNeedAFrozenRosterAndMoveTheVersion(): void
    {
        self::assertSame(2, $this->meeting()->freeze()->guestAdded()->version);

        $this->expectExceptionObject(CommunityException::conflict('Prepare the member roster before adding guests.'));
        $this->meeting()->guestAdded();
    }

    public function testCancellingKeepsTheRosterStateAndEndsTheMeeting(): void
    {
        $cancelled = $this->meeting()->finish(GroupMeeting::CANCELLED, false);

        self::assertSame([GroupMeeting::CANCELLED, false, 1], [$cancelled->status, $cancelled->rosterFrozen, $cancelled->version]);
        $this->expectExceptionObject(CommunityException::conflict('This meeting has already been closed or cancelled.'));
        $cancelled->finish(GroupMeeting::CLOSED, true);
    }

    public function testClosingNeedsAPreparedRosterAndAnEndedOccurrence(): void
    {
        $frozen = $this->meeting()->freeze();
        self::assertSame(GroupMeeting::CLOSED, $frozen->finish(GroupMeeting::CLOSED, true)->status);

        foreach ([[$frozen, false], [$this->meeting(), true]] as [$meeting, $ended]) {
            try {
                $meeting->finish(GroupMeeting::CLOSED, $ended);
                self::fail('closing was accepted');
            } catch (CommunityException $e) {
                self::assertSame('Prepare and review attendance, then close the meeting after it ends.', $e->getMessage());
            }
        }
    }

    public function testOnlyCancelOrCloseFinishesAMeeting(): void
    {
        $this->expectExceptionObject(CommunityException::invalid('Choose cancel or close.'));
        $this->meeting()->finish(GroupMeeting::OPEN, true);
    }
}
