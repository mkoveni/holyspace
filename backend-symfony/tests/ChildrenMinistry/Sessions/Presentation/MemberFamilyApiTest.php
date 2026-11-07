<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Presentation;

use App\ChildrenMinistry\Sessions\Application\Contract\PickupTokens;
use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

/** A guardian's own view of their children, against the real schema. */
final class MemberFamilyApiTest extends ChildrenApiTestCase
{
    private const string ROOT = '/api/member/children';

    private string $programme;
    private string $group;
    private string $room;
    private string $session;
    private string $child;
    private string $guardian;
    private string $authority;
    private string $consent;
    private string $assignment;
    private string $memberAccount;
    private string $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->programme = $this->programme();
        $this->group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $location = $this->create('/locations', ['name' => 'Main campus', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room A', 'capacity' => 12]);
        $policy = $this->create('/consent-policies', ['programmeId' => $this->programme, 'expectedVersion' => 0, 'label' => 'v1', 'documentReference' => 'docs/v1', 'contentHash' => str_repeat('a', 64)]);
        $this->guardian = $this->person('Parent', $this->yearsAgo(35));
        $this->child = $this->person('Child', $this->yearsAgo(8));
        $this->authority = $this->create('/guardian-authorities', ['childId' => $this->child, 'guardianId' => $this->guardian, 'validFrom' => $this->at('-1 day'), 'validUntil' => $this->at('+1 year'), 'evidenceReference' => 'private/guardian']);
        $this->consent = $this->create('/consents', ['childId' => $this->child, 'programmeId' => $this->programme, 'policyId' => $policy, 'authorityId' => $this->authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'acceptedAt' => $this->at(''), 'evidenceReference' => 'signed/consent']);
        $enrolment = $this->enrol($this->programme, $this->child);
        $this->assignment = $this->create('/class-assignments', ['enrolmentId' => $enrolment, 'classId' => $this->group, 'validFrom' => $this->today('-1 day'), 'validUntil' => $this->today('+1 month')]);
        $this->session = $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $this->at('+2 days'), 'endsAt' => $this->at('+2 days +1 hour'), 'seatLimit' => 4]);

        $this->memberAccount = $this->accountWith('familymember', []);
        $this->member = $this->login('familymember');
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('' === $modify ? '+0 seconds' : $modify)->format('Y-m-d\TH:i:s.u\Z');
    }

    private function link(?string $account = null, ?string $person = null): void
    {
        $this->call('PUT', '/api/member-access/'.($account ?? $this->memberAccount), $this->admin, ['personId' => $person ?? $this->guardian, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified in person']);
        self::assertSame(204, $this->code());
    }

    private function path(string $suffix = ''): string
    {
        return self::ROOT.'/'.$this->child.$suffix;
    }

    private function ownGrant(): string
    {
        return $this->create('/pickup-authorizations', ['programmeId' => $this->programme, 'childId' => $this->child, 'collectorId' => $this->guardian, 'authorityId' => $this->authority, 'validFrom' => $this->at('-1 hour'), 'validUntil' => $this->at('+6 months'), 'evidenceReference' => 'Signed guardian pickup']);
    }

    /** A child in the session with an open visit, inserted directly so only the family view is under test. */
    private function visit(): string
    {
        $session = $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $this->at('-10 minutes'), 'endsAt' => $this->at('+2 hours'), 'seatLimit' => 4]);
        $id = Uuid::v4()->toRfc4122();
        $this->connection->executeStatement('INSERT INTO children_attendance (id, child_id, session_id, arrived_at, arrived_by, arrival_request_id) VALUES (?, ?, ?, now(), ?, ?)', [$id, $this->child, $session, $this->adminId, Uuid::v4()->toRfc4122()]);

        return $id;
    }

    private function body(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    /** @return array<mixed> */
    private function label(string $visit, int $version, bool $replace = true, ?string $token = null): array
    {
        $this->call('POST', $this->path("/attendance/$visit/pickup-label"), $token ?? $this->member, ['expectedVersion' => $version, 'replacePrevious' => $replace]);

        return $this->json();
    }

    public function testOnlyExplicitCurrentGuardianAuthorityGrantsOwnReadsWithoutStaffPermissions(): void
    {
        $this->call('GET', self::ROOT);
        self::assertSame(401, $this->code());
        $this->call('GET', self::ROOT, $this->member);
        self::assertSame(200, $this->code());
        self::assertFalse($this->json()['verified']);
        self::assertSame([], $this->json()['items']);

        $this->link();
        $this->call('GET', self::ROOT, $this->member);
        self::assertSame(200, $this->code());
        self::assertTrue($this->json()['verified']);
        self::assertSame([['id' => $this->child, 'name' => 'Child Mabasa']], $this->json()['items']);
        self::assertFalse($this->json()['hasMore']);
        self::assertSame('no-store', $this->client->getResponse()->headers->getCacheControlDirective('no-store') ? 'no-store' : '');
        foreach (['evidenceReference', 'verifiedBy', 'guardianId', 'contacts'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $this->call('GET', $this->path(), $this->member);
        self::assertSame(200, $this->code());
        self::assertSame(['id' => $this->child, 'name' => 'Child Mabasa'], $this->json());
        $this->call('GET', '/api/children/programmes', $this->member);
        self::assertSame(403, $this->code(), 'members hold no staff permission');
        $this->call('GET', self::ROOT.'/'.Uuid::v4()->toRfc4122(), $this->member);
        self::assertSame(404, $this->code());
        $this->call('GET', self::ROOT.'?limit=0', $this->member);
        self::assertSame(400, $this->code());
        $this->call('GET', self::ROOT.'/nope', $this->member);
        self::assertSame(400, $this->code());
    }

    public function testAnUnverifiedAccountCannotReadAChildOrIssueALabel(): void
    {
        foreach (['', '/programmes', '/sessions', '/attendance'] as $suffix) {
            $this->call('GET', $this->path($suffix), $this->member);
            self::assertSame(403, $this->code(), $suffix);
        }
        $this->label(Uuid::v4()->toRfc4122(), 0);
        self::assertSame(403, $this->code());
    }

    public function testAPeopleParentRelationshipDoesNotGrantMinistryAccess(): void
    {
        $this->link();
        $other = $this->person('Other child', $this->yearsAgo(8));
        $this->call('POST', '/api/relationships', $this->admin, ['sourceId' => $this->guardian, 'targetId' => $other, 'type' => 'PARENT_OF']);
        self::assertSame(201, $this->code(), $this->message());

        $this->call('GET', self::ROOT.'/'.$other, $this->member);
        self::assertSame(404, $this->code());
        $this->call('GET', self::ROOT, $this->member);
        self::assertStringNotContainsString($other, $this->body());
        $this->call('GET', self::ROOT.'/'.$other.'/attendance', $this->member);
        self::assertSame(404, $this->code());
    }

    public function testAnotherGuardiansChildIsNotFoundAndTheListIsScopedToTheCaller(): void
    {
        $otherGuardian = $this->person('Someone', $this->yearsAgo(40));
        $otherAccount = $this->accountWith('othermember', []);
        $otherToken = $this->login('othermember');
        $this->link($otherAccount, $otherGuardian);
        $this->link();

        $this->call('GET', $this->path(), $otherToken);
        self::assertSame(404, $this->code());
        $this->call('GET', self::ROOT, $otherToken);
        self::assertSame([], $this->json()['items']);
        $this->call('GET', $this->path('/sessions'), $otherToken);
        self::assertSame(404, $this->code());
        $this->call('GET', $this->path(), $this->member);
        self::assertSame(200, $this->code());
    }

    public function testExpiryRevocationAndFutureAuthorityRemoveAccess(): void
    {
        $this->link();
        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_from = now() + interval '1 minute' WHERE id = ?", [$this->authority]);
        $this->call('GET', self::ROOT, $this->member);
        self::assertStringNotContainsString($this->child, $this->body());

        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_from = now() - interval '2 days', valid_until = now() - interval '1 second' WHERE id = ?", [$this->authority]);
        $this->call('GET', $this->path('/sessions'), $this->member);
        self::assertSame(404, $this->code());

        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_until = now() + interval '1 day', revoked_at = now() WHERE id = ?", [$this->authority]);
        $this->call('GET', $this->path('/programmes'), $this->member);
        self::assertSame(404, $this->code());
    }

    public function testAGuardianWhoIsNoLongerAnAdultOrAvailableSeesNothing(): void
    {
        $this->link();
        $this->connection->executeStatement('UPDATE people_persons SET date_of_birth = ? WHERE id = ?', [$this->yearsAgo(10), $this->guardian]);

        $this->call('GET', self::ROOT, $this->member);
        self::assertSame(400, $this->code(), 'the guardian must be a recorded adult');
    }

    public function testProgrammeReadinessAndSessionPreparationAreMinimalReadOnlySnapshots(): void
    {
        $this->link();
        $this->call('GET', $this->path('/programmes'), $this->member);
        self::assertSame(200, $this->code(), $this->message());
        $programme = $this->json()['items'][0];
        self::assertSame(['Sunday children', 'Juniors', 'Africa/Johannesburg'], [$programme['name'], $programme['className'], $programme['timeZone']]);
        self::assertContains('CONSENT', array_column($programme['readiness'], 'code'));
        self::assertMatchesRegularExpression('/\A\d{4}-\d\d-\d\d\z/', $programme['validFrom']);
        foreach (['evidenceReference', 'signed/consent', 'authorityId'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }

        $this->call('GET', $this->path('/sessions'), $this->member);
        self::assertSame(200, $this->code(), $this->message());
        $row = $this->json()['items'][0];
        self::assertSame([$this->session, 'Africa/Johannesburg', 'Room A', 'Main campus', 'Sunday children', 'Juniors', 'PLANNED', false], [$row['id'], $row['timeZone'], $row['room'], $row['location'], $row['programme'], $row['className'], $row['status'], $row['arrivalWindowOpen']]);
        foreach (['Worker', 'screenedUntil'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        self::assertSame([], $row['issues']);

        $this->connection->executeStatement('UPDATE children_consents SET revoked_at = now() WHERE id = ?', [$this->consent]);
        $this->call('GET', $this->path('/sessions'), $this->member);
        self::assertStringContainsString('Guardian consent', $this->body());
        self::assertSame(0, $this->rows('children_attendance'), 'reads never record attendance');

        $this->connection->executeStatement('UPDATE children_class_assignments SET valid_until = ? WHERE id = ?', [$this->today(), $this->assignment]);
        $this->call('GET', $this->path('/sessions'), $this->member);
        self::assertStringNotContainsString($this->session, $this->body());
    }

    public function testSessionEligibilityUsesItsLocationCalendarDateAtMidnight(): void
    {
        $this->link();
        $local = new DateTimeImmutable($this->today('+1 day').' 00:15', new DateTimeZone('Africa/Johannesburg'));
        $late = $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'endsAt' => $local->modify('+30 minutes')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'seatLimit' => 2]);
        $this->call('GET', $this->path('/sessions'), $this->member);
        self::assertStringContainsString($late, $this->body());

        $this->connection->executeStatement('UPDATE children_class_assignments SET valid_until = ? WHERE id = ?', [$this->today('+1 day'), $this->assignment]);
        $this->call('GET', $this->path('/sessions'), $this->member);
        self::assertStringNotContainsString($late, $this->body(), 'the local date is tomorrow, which the placement no longer covers');
    }

    public function testSessionsAndProgrammesPageWithHasMore(): void
    {
        $this->link();
        $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => $this->at('+3 days'), 'endsAt' => $this->at('+3 days +1 hour'), 'seatLimit' => 4]);

        $this->call('GET', $this->path('/sessions?limit=1'), $this->member);
        self::assertSame([1, true], [count($this->json()['items']), $this->json()['hasMore']]);
        $this->call('GET', $this->path('/sessions?limit=1&offset=1'), $this->member);
        self::assertSame([1, false], [count($this->json()['items']), $this->json()['hasMore']]);
        $this->call('GET', $this->path('/programmes?limit=101'), $this->member);
        self::assertSame(400, $this->code());
    }

    public function testAttendanceOmitsCollectorIdentitySupportNotesAndAuditEvidence(): void
    {
        $this->link();
        $visit = $this->visit();

        $this->call('GET', $this->path('/attendance'), $this->member);
        self::assertSame(200, $this->code(), $this->message());
        $item = $this->json()['items'][0];
        self::assertSame([$visit, false, false, null, 0], [$item['visit']['id'], $item['canRequestPickupLabel'], $item['visit']['labelIssued'], $item['visit']['collectedAt'], $item['visit']['version']]);
        self::assertSame(['Sunday children', 'Juniors', 'Room A', 'Main campus'], [$item['visit']['programme'], $item['visit']['className'], $item['visit']['room'], $item['visit']['location']]);
        foreach (['collectorId', 'authorizationId', 'arrivedBy', 'labelHash', 'pickupCode', 'evidence', 'exceptionReason'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }

        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_from = now() + interval '1 second' WHERE id = ?", [$this->authority]);
        $this->call('GET', $this->path('/attendance'), $this->member);
        self::assertSame(404, $this->code());
    }

    public function testAttendanceStartsFromTheBeginningOfCurrentAuthority(): void
    {
        $this->link();
        $visit = $this->visit();
        $this->connection->executeStatement("UPDATE children_attendance SET arrived_at = now() - interval '3 days' WHERE id = ?", [$visit]);

        $this->call('GET', $this->path('/attendance'), $this->member);
        self::assertSame([], $this->json()['items'], 'the authority began yesterday, so earlier history is not shared');
    }

    public function testTheCollectionQrRequiresOwnGrantReplacesTheOldLabelAndIsShortLived(): void
    {
        $this->link();
        $visit = $this->visit();

        $this->label($visit, 0);
        self::assertSame(409, $this->code(), 'the guardian holds no pickup grant of their own');
        $this->ownGrant();
        $this->call('GET', $this->path('/attendance'), $this->member);
        self::assertTrue($this->json()['items'][0]['canRequestPickupLabel']);

        $issued = $this->label($visit, 0);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([$visit, 1, 1], [$issued['attendanceId'], $issued['labelVersion'], $issued['attendanceVersion']]);
        self::assertSame('no-store', $this->client->getResponse()->headers->getCacheControlDirective('no-store') ? 'no-store' : '');
        $expires = new DateTimeImmutable($issued['expiresAt']);
        self::assertLessThanOrEqual(300, $expires->getTimestamp() - time());
        self::assertGreaterThan(200, $expires->getTimestamp() - time());
        $tokens = self::getContainer()->get(PickupTokens::class);
        \assert($tokens instanceof PickupTokens);
        self::assertSame($tokens->hash($issued['pickupCode']), trim((string) $this->connection->fetchOne('SELECT label_hash FROM children_attendance WHERE id = ?', [$visit])), 'only the hash is stored');
        self::assertSame(1, $this->rows('children_attendance_events', "attendance_id = '$visit' AND type = 'LABEL_ISSUED' AND actor_id = '{$this->memberAccount}'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'pickup.member_label.issued' AND target_id = '$visit' AND actor_id = '{$this->memberAccount}'"));

        $this->label($visit, 0);
        self::assertSame(409, $this->code(), 'the visit moved on to version 1');
        $replacement = $this->label($visit, 1);
        self::assertSame(200, $this->code());
        self::assertSame([2, 2], [$replacement['labelVersion'], $replacement['attendanceVersion']]);
        self::assertNotSame($issued['pickupCode'], $replacement['pickupCode']);
        $this->label($visit, 2, false);
        self::assertSame(400, $this->code(), 'replacement must be confirmed');
    }

    public function testLabelsNeedAnOpenVisitOfTheNamedChildAndLiveAuthority(): void
    {
        $this->link();
        $this->ownGrant();
        $visit = $this->visit();

        $this->label(Uuid::v4()->toRfc4122(), 0);
        self::assertSame(404, $this->code());
        $this->call('POST', self::ROOT.'/'.Uuid::v4()->toRfc4122().'/attendance/'.$visit.'/pickup-label', $this->member, ['expectedVersion' => 0, 'replacePrevious' => true]);
        self::assertSame(404, $this->code(), 'a child the guardian has no authority over');
        $other = $this->person('Sibling', $this->yearsAgo(6));
        $this->connection->executeStatement('INSERT INTO children_guardian_authorities (id, child_id, guardian_id, valid_from, valid_until, evidence_reference, verified_by, version) SELECT ?, ?, guardian_id, valid_from, valid_until, evidence_reference, verified_by, 0 FROM children_guardian_authorities WHERE id = ?', [Uuid::v4()->toRfc4122(), $other, $this->authority]);
        $this->call('POST', self::ROOT.'/'.$other.'/attendance/'.$visit.'/pickup-label', $this->member, ['expectedVersion' => 0, 'replacePrevious' => true]);
        self::assertSame(404, $this->code(), "another child's visit cannot be labelled through a different child");

        $this->connection->executeStatement('UPDATE children_attendance SET checked_out_at = now(), checked_out_by = ?, collector_id = ?, authorization_id = (SELECT id FROM children_pickup_authorizations LIMIT 1), identity_verified = TRUE WHERE id = ?', [$this->adminId, $this->guardian, $visit]);
        $this->label($visit, 0);
        self::assertSame(409, $this->code(), 'collected children have nothing to label');
        $this->call('GET', $this->path('/attendance'), $this->member);
        self::assertFalse($this->json()['items'][0]['canRequestPickupLabel']);
    }

    public function testRevokedPickupRightsCannotIssueCollectionLabels(): void
    {
        $this->link();
        $own = $this->ownGrant();
        $visit = $this->visit();
        $this->connection->executeStatement('UPDATE children_pickup_authorizations SET revoked_at = now() WHERE id = ?', [$own]);

        $this->label($visit, 0);
        self::assertSame(409, $this->code());
        $this->call('GET', $this->path('/attendance'), $this->member);
        self::assertFalse($this->json()['items'][0]['canRequestPickupLabel']);
    }

    public function testALabelNeverOutlivesTheAuthorityItRestsOn(): void
    {
        $this->link();
        $this->ownGrant();
        $visit = $this->visit();
        $this->connection->executeStatement("UPDATE children_guardian_authorities SET valid_until = now() + interval '90 seconds' WHERE id = ?", [$this->authority]);

        $issued = $this->label($visit, 0);

        self::assertSame(200, $this->code(), $this->message());
        self::assertLessThanOrEqual(90, (new DateTimeImmutable($issued['expiresAt']))->getTimestamp() - time());
    }

    public function testStaffReviewOfArrivalReferencesDoesNotGrantMemberStationAccessOrRecordAttendance(): void
    {
        $this->link();
        $route = "/api/children/sessions/{$this->session}/arrival-reference/{$this->child}";

        $this->call('GET', $route, $this->member);
        self::assertSame(403, $this->code());
        $this->call('GET', $route);
        self::assertSame(401, $this->code());
        $this->call('GET', $route, $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(['child' => ['id' => $this->child, 'name' => 'Child Mabasa'], 'issues' => []], $this->json());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        foreach (['guardianId', 'evidence', 'pickupCode'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $this->call('GET', "/api/children/sessions/{$this->session}/arrival-reference/".Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/children/sessions/'.Uuid::v4()->toRfc4122()."/arrival-reference/{$this->child}", $this->admin);
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->rows('children_attendance'));

        $this->connection->executeStatement('UPDATE children_consents SET revoked_at = now() WHERE id = ?', [$this->consent]);
        $this->call('GET', $route, $this->admin);
        self::assertStringContainsString('Guardian consent', $this->body());
    }
}
