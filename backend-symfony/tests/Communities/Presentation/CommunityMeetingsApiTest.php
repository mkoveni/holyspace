<?php

declare(strict_types=1);

namespace App\Tests\Communities\Presentation;

use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Port of the meeting tests in Java `CommunityApiIntegrationTest` (`meetingPreparationFreezes...`,
 * `correctionsRequireVersions...`, `frozenGroupRostersPreventEventRescheduling...`, `closingKeepsUnrecordedPeople...`),
 * plus scheduling, replay, scope and input rules.
 */
final class CommunityMeetingsApiTest extends CommunitiesApiTestCase
{
    use GroupMeetingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createVenue();
    }

    public function testMeetingPreparationFreezesMembershipAndGuestsDoNotJoinTheGroup(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Expected');
        $member = $this->join($group, $person);
        $meeting = $this->meeting($group);
        $this->call('GET', self::ROOT."/$group/meetings", $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame(0, $this->rows('community_roster'));
        $this->prepare($meeting, 0);
        $this->noContent();
        self::assertSame(1, $this->rows('community_roster'));
        $this->call('POST', self::ROOT."/memberships/$member/leave", $this->admin, ['expectedVersion' => 0, 'reason' => 'Membership ended after roster preparation']);
        self::assertSame(204, $this->code());
        $this->call('GET', self::ROOT."/meetings/$meeting/roster", $this->admin);
        self::assertStringContainsString('Expected Mabasa', $this->body());
        $guest = $this->person('Guest');
        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 1, 'personId' => $guest, 'reason' => 'Welcomed a guest']);
        $this->noContent();
        self::assertSame(0, $this->rows('community_memberships', "person_id = '$guest'"));
        self::assertSame(2, $this->rows('community_roster'));
        $this->mark($this->rosterRow($meeting, $person), 0, 'PRESENT');
        $this->noContent();

        $this->call('GET', self::ROOT."/meetings/$meeting/roster", $this->admin);
        $roster = $this->json();
        self::assertSame(2, $roster['total']);
        self::assertSame(['meetingId' => $meeting, 'expected' => 2, 'present' => 1, 'absent' => 0, 'excused' => 0, 'unrecorded' => 1, 'guests' => 1], $roster['counts']);
        $guestRow = array_values(array_filter($roster['roster'], static fn (array $r): bool => $r['guest']))[0];
        self::assertSame(['meetingId' => $meeting, 'personId' => $guest, 'membershipId' => null, 'guest' => true, 'status' => 'UNRECORDED', 'version' => 0, 'name' => 'Guest Mabasa'], array_diff_key($guestRow, ['id' => 0]));
        $this->call('GET', self::ROOT."/$group/history", $this->admin);
        self::assertSame(['ATTENDANCE_PRESENT', 'GUEST_ADDED', 'MEMBER_LEFT', 'ROSTER_PREPARED', 'MEETINGS_SCHEDULED'], array_slice(array_column($this->json(), 'action'), 0, 5));
    }

    public function testCorrectionsRequireVersionsAndReasonsAndReportsKeepUnrecordedDistinct(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Attendee');
        $this->join($group, $person);
        $this->join($group, $this->person('Not marked'));
        $meeting = $this->meeting($group);
        $this->prepare($meeting, 0);
        $this->noContent();
        $row = $this->rosterRow($meeting, $person);
        $this->mark($row, 0, 'PRESENT');
        $this->noContent();
        $this->mark($row, 0, 'ABSENT');
        self::assertSame(409, $this->code());
        $this->call('PUT', self::ROOT."/roster/$row", $this->admin, ['expectedVersion' => 1, 'status' => 'ABSENT', 'reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->mark($row, 1, 'EXCUSED');
        $this->noContent();
        $this->mark($row, 2, 'EXCUSED');
        self::assertSame(204, $this->code(), 'an unchanged status is accepted');
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT version FROM community_roster WHERE id = ?', [$row]), 'and writes nothing');

        $this->call('GET', self::ROOT."/$group/meetings", $this->admin);
        $counts = $this->json()['meetings'][0]['counts'];
        self::assertSame([1, 1, 0, 0, 2], [$counts['excused'], $counts['unrecorded'], $counts['absent'], $counts['present'], $counts['expected']]);
        $this->call('GET', self::ROOT."/$group/history", $this->admin);
        self::assertStringContainsString('ATTENDANCE_PRESENT', $this->body());
        self::assertStringContainsString('ATTENDANCE_EXCUSED', $this->body());
        self::assertStringContainsString('Verified by group coordinator', $this->body());
        self::assertSame(2, $this->rows('community_history', "action LIKE 'ATTENDANCE_%'"));
    }

    public function testFrozenGroupRostersPreventEventReschedulingAndCancellationPreservesEvidence(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Present');
        $this->join($group, $person);
        $meeting = $this->meeting($group);
        $this->prepare($meeting, 0);
        $this->mark($this->rosterRow($meeting, $person), 0, 'PRESENT');
        $this->noContent();
        $occurrence = $this->occurrence($meeting);
        $this->call('GET', "/api/events/occurrences/$occurrence", $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        $source = $this->json()['occurrence'];

        $reschedule = ['occurrenceId' => $occurrence, 'expectedVersion' => $source['version'], 'action' => 'RESCHEDULE', 'scope' => 'THIS', 'date' => '2099-01-01', 'startsAt' => '09:00', 'endsAt' => '10:00', 'endDayOffset' => 0, 'venueId' => $this->venue];
        $this->call('POST', '/api/events/occurrences/preview', $this->admin, ['draft' => $reschedule]);
        self::assertSame(409, $this->code());
        self::assertSame('A linked group has prepared its dated roster. Cancel this gathering instead of moving its attendance evidence.', $this->message());

        $cancel = ['occurrenceId' => $occurrence, 'expectedVersion' => $source['version'], 'action' => 'CANCEL', 'scope' => 'THIS'];
        $this->call('POST', '/api/events/occurrences/preview', $this->admin, ['draft' => $cancel]);
        self::assertSame(200, $this->code(), $this->message());
        $this->call('POST', '/api/events/occurrences/cancel', $this->admin, ['draft' => $cancel, 'reviewHash' => $this->json()['hash'], 'reason' => 'Weather']);
        $this->noContent();
        self::assertSame('PRESENT', $this->connection->fetchOne('SELECT status FROM community_roster'));
        $this->mark($this->rosterRow($meeting, $person), 1, 'EXCUSED');
        self::assertSame(204, $this->code(), 'corrections stay possible after the occurrence is cancelled');

        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 1, 'personId' => $this->person('Late'), 'reason' => 'Arrived']);
        self::assertSame(409, $this->code());
        self::assertSame('This meeting is closed to new attendance.', $this->message());
    }

    public function testReschedulingBeforePreparationMovesTheLinkedMeeting(): void
    {
        $group = $this->community('GROUP', 'Home');
        $meeting = $this->meeting($group);
        $occurrence = $this->occurrence($meeting);
        $this->call('GET', "/api/events/occurrences/$occurrence", $this->admin);
        $draft = ['occurrenceId' => $occurrence, 'expectedVersion' => $this->json()['occurrence']['version'], 'action' => 'RESCHEDULE', 'scope' => 'THIS', 'date' => '2099-01-01', 'startsAt' => '09:00', 'endsAt' => '10:00', 'endDayOffset' => 0, 'venueId' => $this->venue];
        $this->call('POST', '/api/events/occurrences/preview', $this->admin, ['draft' => $draft]);
        self::assertSame(200, $this->code(), $this->message());
        $this->call('POST', '/api/events/occurrences/reschedule', $this->admin, ['draft' => $draft, 'reviewHash' => $this->json()['hash'], 'reason' => 'Moved']);
        $this->noContent();

        $this->call('GET', self::ROOT."/$group/meetings", $this->admin);
        $listed = $this->json()['meetings'][0];
        self::assertSame('2099-01-01T09:00:00Z', $listed['meeting']['scheduledAt']);
        self::assertSame('2099-01-01T09:00:00Z', $listed['date']['startsAt']);
        self::assertSame(0, $listed['meeting']['version'], 'following the occurrence does not move the meeting version');
        $this->prepare($meeting, 0);
        self::assertSame(409, $this->code());
        self::assertSame('Prepare the meeting roster from two hours before it starts.', $this->message());
    }

    public function testClosingKeepsUnrecordedPeopleAndAllowsReasonedCorrections(): void
    {
        $group = $this->community('GROUP', 'Home');
        $person = $this->person('Attendee');
        $this->join($group, $person);
        $unmarked = $this->person('Unmarked');
        $this->join($group, $unmarked);
        $meeting = $this->meeting($group);
        $this->prepare($meeting, 0);
        $row = $this->rosterRow($meeting, $person);
        $this->mark($row, 0, 'PRESENT');
        $this->noContent();
        $close = ['expectedVersion' => 1, 'status' => 'CLOSED', 'reason' => 'Reviewed attendance'];
        $this->call('POST', self::ROOT."/meetings/$meeting/finish", $this->admin, $close);
        self::assertSame(409, $this->code());
        self::assertSame('Prepare and review attendance, then close the meeting after it ends.', $this->message());
        $now = new DateTimeImmutable();
        $this->connection->update('event_occurrences', [
            'starts_at' => Timestamps::toDatabase($now->modify('-2 hours')),
            'ends_at' => Timestamps::toDatabase($now->modify('-1 hour')),
        ], ['id' => $this->occurrence($meeting)]);
        $this->call('POST', self::ROOT."/meetings/$meeting/finish", $this->admin, $close);
        $this->noContent();
        $this->mark($row, 1, 'EXCUSED');
        $this->noContent();
        self::assertSame(2, $this->rows('community_roster'), 'unrecorded people stay on the closed roster');
        $this->mark($this->rosterRow($meeting, $unmarked), 0, 'PRESENT');
        self::assertSame(409, $this->code(), 'a first recording needs an open meeting');
        $this->call('POST', self::ROOT."/meetings/$meeting/finish", $this->admin, ['status' => 'CANCELLED', 'expectedVersion' => 2] + $close);
        self::assertSame(409, $this->code());
        self::assertSame('This meeting has already been closed or cancelled.', $this->message());
        $this->call('GET', self::ROOT."/$group/history", $this->admin);
        self::assertSame(['ATTENDANCE_EXCUSED', 'MEETING_CLOSED'], array_slice(array_column($this->json(), 'action'), 0, 2));
    }

    public function testSchedulingNeedsAReviewedScheduleForAnActiveGroupAndReplays(): void
    {
        $group = $this->community('GROUP', 'Home');
        $schedule = $this->schedule(3, 'WEEKLY');
        $this->call('POST', self::ROOT."/$group/meeting-preview", $this->admin, ['schedule' => $schedule]);
        self::assertSame(200, $this->code(), $this->message());
        self::assertCount(3, $this->json()['dates']);
        self::assertSame([null, 'Lounge', 'SCHEDULED', 0, 'UTC'], [$this->json()['dates'][0]['id'], $this->json()['dates'][0]['venue'], $this->json()['dates'][0]['status'], $this->json()['dates'][0]['revision'], $this->json()['dates'][0]['timeZone']]);
        $body = ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'schedule' => $schedule, 'reviewHash' => $this->json()['hash']];

        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, ['reviewHash' => str_repeat('0', 64)] + $body);
        self::assertSame(409, $this->code(), 'an unreviewed schedule is refused by Events');
        self::assertSame(0, $this->rows('community_meetings'));
        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, $body);
        self::assertSame(201, $this->code(), $this->message());
        $first = (string) $this->json()['id'];
        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, $body);
        self::assertSame([201, $first], [$this->code(), $this->json()['id']], 'the same request returns the first meeting');
        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, ['expectedVersion' => 1] + $body);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was already used.', $this->message());
        self::assertSame(3, $this->rows('community_meetings'));
        self::assertSame(1, $this->rows('community_history', "action = 'MEETINGS_SCHEDULED' AND reason = 'Scheduled 3 meetings.'"));

        $this->call('GET', self::ROOT."/$group", $this->admin);
        self::assertSame(1, $this->json()['community']['version'], 'scheduling moves the community version');
        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, ['requestId' => Uuid::v4()->toRfc4122()] + $body);
        self::assertSame(409, $this->code());
        self::assertSame('These details changed. Refresh and review before saving.', $this->message());

        $this->call('GET', self::ROOT."/$group/meetings?limit=2", $this->admin);
        self::assertSame(3, $this->json()['total']);
        self::assertCount(2, $this->json()['meetings']);
        $dates = array_map(static fn (array $m): string => $m['meeting']['scheduledAt'], $this->json()['meetings']);
        self::assertGreaterThan($dates[1], $dates[0], 'newest first');
        $listed = $this->json()['meetings'][0];
        self::assertSame(['communityId' => $group, 'status' => 'OPEN', 'rosterFrozen' => false, 'version' => 0], array_intersect_key($listed['meeting'], ['communityId' => 0, 'status' => 0, 'rosterFrozen' => 0, 'version' => 0]));
        self::assertSame($listed['meeting']['occurrenceId'], $listed['date']['id']);
        self::assertSame(0, $listed['counts']['expected']);

        $ministry = $this->community('MINISTRY', 'Care');
        $this->call('POST', self::ROOT."/$ministry/meetings", $this->admin, ['requestId' => Uuid::v4()->toRfc4122()] + $body);
        self::assertSame(409, $this->code());
        self::assertSame('Schedule meetings for an active group.', $this->message());
        $this->call('POST', self::ROOT.'/'.Uuid::v4()->toRfc4122().'/meetings', $this->admin, $body);
        self::assertSame(404, $this->code());
    }

    public function testPreparationWindowRosterCapRulesAndGuests(): void
    {
        $group = $this->community('GROUP', 'Home');
        $later = $this->meeting($group, $this->schedule(startsIn: 3 * 86400));
        $this->prepare($later, 0);
        self::assertSame(409, $this->code());
        self::assertSame('Prepare the meeting roster from two hours before it starts.', $this->message());

        $meeting = $this->meeting($group, null, 1);
        $guest = $this->person('Guest');
        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 0, 'personId' => $guest, 'reason' => 'Early']);
        self::assertSame(409, $this->code());
        self::assertSame('Prepare the member roster before adding guests.', $this->message());
        $this->prepare($meeting, 0);
        $this->noContent();
        $this->prepare($meeting, 1);
        self::assertSame(204, $this->code(), 'preparing again keeps the roster');
        $this->call('GET', self::ROOT."/$group/meetings", $this->admin);
        $versions = array_column(array_column($this->json()['meetings'], 'meeting'), 'version', 'id');
        self::assertSame(1, $versions[$meeting]);
        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 1, 'personId' => $guest, 'reason' => 'Welcome']);
        $this->noContent();
        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 2, 'personId' => $guest, 'reason' => 'Again']);
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('community_roster', "person_id = '$guest'"), 'a person is listed once');
        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 2, 'personId' => $guest, 'reason' => 'Stale']);
        self::assertSame(409, $this->code());
        $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, ['expectedVersion' => 3, 'personId' => Uuid::v4()->toRfc4122(), 'reason' => 'Unknown']);
        self::assertSame(404, $this->code());

        $this->call('POST', self::ROOT."/meetings/$meeting/finish", $this->admin, ['expectedVersion' => 3, 'status' => 'CANCELLED', 'reason' => 'Rain']);
        $this->noContent();
        self::assertSame(['CANCELLED', 1], [$this->connection->fetchOne('SELECT status FROM community_meetings WHERE id = ?', [$meeting]), $this->rows('community_roster')]);
    }

    public function testLeadersRunTheirOwnGroupsMeetingsOnlyAndReadersHaveNoAccess(): void
    {
        $group = $this->community('GROUP', 'Home');
        $other = $this->community('GROUP', 'Other');
        $person = $this->person('Member');
        $this->join($group, $person);
        $leader = $this->leader('leader', $this->join($group, $this->person('Leader')));
        $meeting = $this->meeting($group);
        $elsewhere = $this->meeting($other);

        $this->call('POST', self::ROOT."/$group/meeting-preview", $leader, ['schedule' => $this->schedule()]);
        self::assertSame(403, $this->code(), 'previewing also needs events.manage');
        $this->call('POST', self::ROOT."/$group/meetings", $leader, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 1, 'schedule' => $this->schedule(), 'reviewHash' => 'x']);
        self::assertSame(403, $this->code(), 'only managers schedule');
        $this->prepare($meeting, 0, $leader);
        $this->noContent();
        $this->call('GET', self::ROOT."/meetings/$meeting/roster", $leader);
        self::assertSame(200, $this->code());
        $this->call('GET', self::ROOT."/$group/meetings", $leader);
        self::assertSame(200, $this->code());
        $this->mark($this->rosterRow($meeting, $person), 0, 'PRESENT', $leader);
        $this->noContent();
        $this->call('POST', self::ROOT."/meetings/$meeting/finish", $leader, ['expectedVersion' => 1, 'status' => 'CANCELLED', 'reason' => 'Rain']);
        self::assertSame(403, $this->code(), 'only managers cancel');

        $this->prepare($elsewhere, 0, $leader);
        self::assertSame(403, $this->code());
        self::assertSame('This community is outside your current leadership scope.', $this->message());
        $this->call('GET', self::ROOT."/meetings/$elsewhere/roster", $leader);
        self::assertSame(403, $this->code());
        $this->call('GET', self::ROOT."/$other/meetings", $leader);
        self::assertSame(403, $this->code());

        $this->accountWith('reader', ['ministries.read']);
        $reader = $this->login('reader');
        $this->call('GET', self::ROOT."/$group/meetings", $reader);
        self::assertSame(403, $this->code(), 'directory readers cannot open meetings');
        $this->call('GET', self::ROOT."/meetings/$meeting/roster", $reader);
        self::assertSame(403, $this->code());
        $this->accountWith('nobody', ['people.read']);
        $this->call('POST', self::ROOT."/meetings/$meeting/prepare", $this->login('nobody'), []);
        self::assertSame(403, $this->code(), 'refused before the body is read');
        $this->call('GET', self::ROOT."/$group/meetings");
        self::assertSame(401, $this->code());
    }

    public function testMalformedInputIsRefused(): void
    {
        $group = $this->community('GROUP', 'Home');
        $meeting = $this->meeting($group);
        $this->prepare($meeting, 0);
        $row = (string) $this->connection->fetchOne('SELECT id FROM community_roster LIMIT 1') ?: Uuid::v4()->toRfc4122();

        foreach ([
            ['schedule' => null], ['schedule' => ['startsAt' => '25:00'] + $this->schedule()], ['schedule' => ['firstDate' => '2026/01/01'] + $this->schedule()],
            ['schedule' => ['venueId' => 'nope'] + $this->schedule()], ['schedule' => array_diff_key($this->schedule(), ['count' => 0])],
        ] as $change) {
            $this->call('POST', self::ROOT."/$group/meeting-preview", $this->admin, $change);
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        $body = ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 1, 'schedule' => $this->schedule(), 'reviewHash' => 'h'];
        foreach ([['requestId' => null], ['requestId' => 'nope'], ['reviewHash' => ' '], ['reviewHash' => str_repeat('h', 151)], ['expectedVersion' => -1], ['schedule' => null]] as $change) {
            $this->call('POST', self::ROOT."/$group/meetings", $this->admin, $change + $body);
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        $this->call('POST', self::ROOT."/$group/meetings", $this->admin, array_diff_key($body, ['expectedVersion' => 0]));
        self::assertSame(400, $this->code(), 'a missing version is a 400');

        $this->call('POST', self::ROOT."/meetings/$meeting/prepare", $this->admin, []);
        self::assertSame(400, $this->code());
        foreach ([['status' => 'LATE'], ['status' => ' '], ['reason' => ' '], ['reason' => str_repeat('r', 2001)]] as $change) {
            $this->call('PUT', self::ROOT."/roster/$row", $this->admin, $change + ['expectedVersion' => 0, 'status' => 'PRESENT', 'reason' => 'Seen']);
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach ([['status' => 'OPEN'], ['status' => null], ['reason' => ' ']] as $change) {
            $this->call('POST', self::ROOT."/meetings/$meeting/finish", $this->admin, $change + ['expectedVersion' => 1, 'status' => 'CANCELLED', 'reason' => 'Rain']);
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach ([['personId' => 'nope'], ['personId' => null], ['reason' => '']] as $change) {
            $this->call('POST', self::ROOT."/meetings/$meeting/guests", $this->admin, $change + ['expectedVersion' => 1, 'personId' => Uuid::v4()->toRfc4122(), 'reason' => 'Hi']);
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        $this->call('GET', self::ROOT."/$group/meetings?limit=0", $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT."/meetings/$meeting/roster?offset=-1", $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT.'/meetings/not-a-uuid/roster', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT.'/meetings/'.Uuid::v4()->toRfc4122().'/roster', $this->admin);
        self::assertSame(404, $this->code());
        $this->call('PUT', self::ROOT.'/roster/'.Uuid::v4()->toRfc4122(), $this->admin, ['expectedVersion' => 0, 'status' => 'PRESENT', 'reason' => 'Seen']);
        self::assertSame(404, $this->code());
        $this->call('POST', self::ROOT.'/meetings/'.Uuid::v4()->toRfc4122().'/finish', $this->admin, ['expectedVersion' => 0, 'status' => 'CLOSED', 'reason' => 'Done']);
        self::assertSame(404, $this->code());
    }
}
