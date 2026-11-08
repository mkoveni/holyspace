<?php

declare(strict_types=1);

namespace App\Tests\Communities\Application;

use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityView;
use App\Communities\Application\Contract\MembershipView;
use App\Communities\Application\NoticeReview;
use App\Events\Application\Contract\CommunitySchedule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Java `CommunityHash` stores request hashes in `community_requests` and review hashes on notices and deliveries, so a
 * retry or a send against either backend must match. Every expected value below was captured from Java's compiled
 * `CommunityHash.of` and `CommunityMessaging.hash` with the real argument types (UUID, `long`, `LocalDate`,
 * `CommunityCalendar.Schedule`); `docs/porting/vectors/CommunityHashVectors.java` regenerates them.
 */
final class CommunityHashTest extends TestCase
{
    private const string ACTOR = '11111111-1111-4111-8111-111111111111';
    private const string COMMUNITY = '22222222-2222-4222-8222-222222222222';
    private const string PERSON = '33333333-3333-4333-8333-333333333333';
    private const string MEMBERSHIP = '44444444-4444-4444-8444-444444444444';
    private const string ACCOUNT = '55555555-5555-4555-8555-555555555555';
    private const string CONTACT = '66666666-6666-4666-8666-666666666666';
    private const string VENUE = '77777777-7777-4777-8777-777777777777';
    private const string PARENT = '88888888-8888-4888-8888-888888888888';

    public function testCreateMatchesJava(): void
    {
        self::assertSame('5221dcfee4da8132c08f03c75c5f431820c107b56a4c86fdb9a104943aa67881', CommunityHash::of(self::ACTOR, 'CREATE', 'GROUP', 'Home group', 'Grüße 漢字', self::PARENT, 'North'));
        self::assertSame('85c53fbeb75f0b8266f260adfd9a655486082079c94c262c2d197f6d94c7ffe3', CommunityHash::of(self::ACTOR, 'CREATE', 'MINISTRY', 'Youth', null, null, null));
    }

    public function testJoinAndAppointMatchJava(): void
    {
        self::assertSame('ca2d91e0626418a0051b94ef40a49b77937431fd370042ea1e3ae60b72dfd8fa', CommunityHash::of(self::ACTOR, 'JOIN', self::COMMUNITY, self::PERSON));
        self::assertSame('0772b69a0711e277bcd619be96770aafcf809a9a432c452a12e58a5bcc0dabdb', CommunityHash::of(self::ACTOR, 'APPOINT', self::MEMBERSHIP, 3, 'LEADER', self::ACCOUNT, 'Leads Tuesdays'));
        self::assertSame('129c30b49c949d5d72f488b7a0d0d589db892aaf204dbf25e2ba5ada16e612f1', CommunityHash::of(self::ACTOR, 'APPOINT', self::MEMBERSHIP, 0, 'ASSISTANT', null, null));
    }

    public function testFollowUpMatchesJavasLocalDateText(): void
    {
        self::assertSame('3a54208541c5e2880826d4de9eb9de5c3042d9662f17884f32c21a9294f073f5', CommunityHash::of(self::ACTOR, 'FOLLOWUP', self::COMMUNITY, self::PERSON, 'Call after visit', '2026-03-09'));
    }

    public function testScheduleMatchesJavasRecordText(): void
    {
        $weekly = CommunitySchedule::of(strtoupper(self::VENUE), 'Africa/Johannesburg', 'WEEKLY', 1, 6, '2026-03-03', '18:30:00', '20:00', 0);
        $monthly = CommunitySchedule::of(self::VENUE, 'UTC', 'MONTHLY', 2, 3, '2026-12-31', '23:00:15', '01:00:00.5', 1);

        self::assertSame('Schedule[venueId=77777777-7777-4777-8777-777777777777, timeZone=UTC, frequency=MONTHLY, interval=2, count=3, firstDate=2026-12-31, startsAt=23:00:15, endsAt=01:00:00.500, endDayOffset=1]', $monthly->javaString());
        self::assertSame('dd28b3428369edc4bc69c8e215d4eadf20cf43d12840f6f8e694a62fc7547c96', CommunityHash::of(self::ACTOR, 'SCHEDULE', self::COMMUNITY, 4, $weekly->javaString(), 'abc123'));
        self::assertSame('dedba1f7e1187d80bb7398905baee77aae0e7cb0983105a3fbe9dd5e5db81a76', CommunityHash::of(self::ACTOR, 'SCHEDULE', self::COMMUNITY, 0, $monthly->javaString(), 'def456'));
    }

    public function testNoticeReviewAndApprovalMatchJava(): void
    {
        $membership = new MembershipView(self::MEMBERSHIP, self::COMMUNITY, self::PERSON, new DateTimeImmutable('2026-01-01T00:00:00Z'), null, 2);
        $community = new CommunityView(self::COMMUNITY, 'GROUP', 'Home group', '', null, '', true, 7);

        self::assertSame('c0a947c43ce869aab91f0b568e752b9f799f6c23c5e310353b5e34041a0e936e', NoticeReview::hash($membership, $community, self::CONTACT, 'ada@example.org', 'A note from Home group', "<p>Hello Ada</p>\n<p>Grüße</p>", 1));
        self::assertSame('3b84ed43227c910dd1b7d875cb2c21b298265dd9aec6fc3cb83b3e00309fc30b', CommunityHash::of(self::ACTOR, self::MEMBERSHIP, self::CONTACT, '  Hello Ada  ', 'f00d', 'Checked by phone'));
    }

    public function testLengthPrefixesKeepFieldBoundaries(): void
    {
        self::assertSame(hash('sha256', ''), CommunityHash::of());
        self::assertNotSame(CommunityHash::of('ab', 'c'), CommunityHash::of('a', 'bc'));
        self::assertNotSame(CommunityHash::of(null), CommunityHash::of(''));
        self::assertSame(CommunityHash::of(null), CommunityHash::of('null'));
        self::assertSame(CommunityHash::of(true, 0), CommunityHash::of('true', '0'));
    }
}
