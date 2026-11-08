<?php

declare(strict_types=1);

namespace App\Tests\Communities\Domain;

use App\Communities\Domain\CommunityException;
use App\Communities\Domain\Membership;
use App\Communities\Domain\RosterEntry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RosterEntryTest extends TestCase
{
    public function testMembersAndGuestsStartUnrecorded(): void
    {
        $member = RosterEntry::member('r1', 'm', Membership::start('ms', 'c', 'p', new DateTimeImmutable()));
        $guest = RosterEntry::guest('r2', 'm', 'g');

        self::assertSame(['p', 'ms', false, RosterEntry::UNRECORDED, 0], [$member->personId, $member->membershipId, $member->guest, $member->status, $member->version]);
        self::assertSame(['g', null, true, RosterEntry::UNRECORDED, 0], [$guest->personId, $guest->membershipId, $guest->guest, $guest->status, $guest->version]);
        self::assertTrue($guest->isFirstRecording());
    }

    public function testRecordingMovesTheVersionSoLaterOnesAreCorrections(): void
    {
        $recorded = RosterEntry::guest('r', 'm', 'g')->record('PRESENT')->record('EXCUSED');

        self::assertSame(['EXCUSED', 2], [$recorded->status, $recorded->version]);
        self::assertFalse($recorded->isFirstRecording());
    }

    public function testOnlyKnownStatusesAreAccepted(): void
    {
        self::assertSame('UNRECORDED', RosterEntry::requireStatus('UNRECORDED'));

        $this->expectExceptionObject(CommunityException::invalid('Choose an attendance status.'));
        RosterEntry::requireStatus('present');
    }
}
