<?php

declare(strict_types=1);

namespace App\Tests\Communities\Domain;

use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Membership;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** Port of Java `MembershipTest`, plus the community, leadership and text rules. */
final class MembershipTest extends TestCase
{
    public function testMembershipPeriodsAreHalfOpenAndEndingDoesNotRewriteTheStart(): void
    {
        $from = new DateTimeImmutable('2026-10-27T10:00:00Z');
        $until = $from->modify('+3600 seconds');
        $ended = Membership::start('m1', 'c1', 'p1', $from)->end($until);

        self::assertTrue($ended->covers($from));
        self::assertFalse($ended->covers($until));
        self::assertEquals($from, $ended->startedAt);
        self::assertSame(1, $ended->version);
        $this->expectException(CommunityException::class);
        $ended->end($until);
    }

    public function testMinistriesCannotHaveAParentAndNamesAreRequired(): void
    {
        foreach ([
            static fn (): Community => Community::create('c1', 'MINISTRY', 'Name', '', 'parent', ''),
            static fn (): Community => Community::create('c1', 'GROUP', ' ', '', null, ''),
            static fn (): Community => Community::create('c1', 'TEAM', 'Name', '', null, ''),
            static fn (): Community => Community::create('c1', 'GROUP', str_repeat('n', 151), '', null, ''),
        ] as $build) {
            try {
                $build();
                self::fail('must be refused');
            } catch (CommunityException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }

    public function testTextIsStrippedAndOptionalTextMayBeEmpty(): void
    {
        $community = Community::create('c1', 'GROUP', "  Home\n", null, null, ' ');

        self::assertSame(['Home', '', ''], [$community->name, $community->description, $community->campus]);
        self::assertSame(['Renamed', 1, false], [$community->update('Renamed', '', null, '', false)->name, $community->update('Renamed', '', null, '', false)->version, $community->update('Renamed', '', null, '', false)->active]);
        self::assertSame('x', CommunityRules::text(' x ', 3), 'the limit applies before stripping, as in Java');
        $this->expectExceptionMessage('Enter text of 1–3 characters.');
        CommunityRules::text(' xyz ', 3);
    }

    public function testLeadershipRolesAndEnding(): void
    {
        $now = new DateTimeImmutable('2026-10-27T10:00:00Z');
        $leader = Leadership::appoint('l1', 'm1', 'c1', null, 'CO_LEADER', $now);
        $ended = $leader->end($now->modify('+1 day'));

        self::assertSame(1, $ended->version);
        self::assertNotNull($ended->endedAt);
        try {
            $ended->end($now);
            self::fail('an ended appointment cannot end again');
        } catch (CommunityException $expected) {
            self::assertSame('This appointment has already ended.', $expected->getMessage());
        }
        $this->expectExceptionMessage('Choose leader or co-leader.');
        Leadership::appoint('l2', 'm1', 'c1', null, 'CHAIR', $now);
    }
}
