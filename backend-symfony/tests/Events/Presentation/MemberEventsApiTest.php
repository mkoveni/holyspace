<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class MemberEventsApiTest extends EventsApiTestCase
{
    private string $event;
    private string $occurrence;
    private string $access;
    private string $door;
    private string $ada;
    private string $adaPerson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('access', ['identity.manage', 'people.manage']);
        $this->access = $this->login('access');
        $this->accountWith('door', ['events.attendance.manage']);
        $this->door = $this->login('door');
        $this->event = $this->event($this->draft((new DateTimeImmutable('+2 days'))->format('Y-m-d'), 1));
        $this->occurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences LIMIT 1');
        $this->connection->executeStatement("UPDATE event_registration_policies SET open = TRUE WHERE occurrence_id = '{$this->occurrence}'");
        [, $this->ada, $this->adaPerson] = $this->member('ada', 'Ada');
    }

    /** @return array{string, string, string} account id, bearer token, person id */
    private function member(string $username, string $name): array
    {
        $account = $this->account($username);
        $person = $this->person($name);
        $this->call('PUT', "/api/member-access/$account", $this->access, ['personId' => $person, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified in person']);
        self::assertSame(204, $this->code());

        return [$account, $this->login($username), $person];
    }

    private function publish(bool $enabled = true, int $version = 0): void
    {
        $this->call('PUT', "/api/events/{$this->event}/member-offer", $this->manager, ['enabled' => $enabled, 'expectedVersion' => $version]);
        self::assertSame(204, $this->code());
    }

    private function startsIn(string $interval): void
    {
        $this->connection->executeStatement("UPDATE event_occurrences SET starts_at = now() + interval '$interval', ends_at = now() + interval '$interval' + interval '90 minutes' WHERE id = ?", [$this->occurrence]);
    }

    private function book(?string $token = null, ?int $version = null): void
    {
        $this->call('POST', "/api/member/events/{$this->occurrence}/registration", $token ?? $this->ada, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => $version]);
    }

    public function testStaffPublishAndWithdrawTheEventWithAVersion(): void
    {
        $this->call('GET', "/api/events/{$this->event}/member-offer", $this->manager);
        self::assertSame(['enabled' => false, 'version' => 0], $this->json());

        $this->call('PUT', "/api/events/{$this->event}/member-offer", $this->manager, ['enabled' => true, 'expectedVersion' => 3]);
        self::assertSame(409, $this->code());
        $this->publish();
        $this->call('GET', "/api/events/{$this->event}/member-offer", $this->manager);
        self::assertSame(['enabled' => true, 'version' => 1], $this->json());
        $this->publish(false, 1);
        self::assertSame(2, $this->rows('event_audit', "action = 'event.member_offer.changed'"));

        $this->call('PUT', '/api/events/'.Uuid::v4()->toRfc4122().'/member-offer', $this->manager, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(404, $this->code());
        $this->call('PUT', "/api/events/{$this->event}/member-offer", $this->reader, ['enabled' => true, 'expectedVersion' => 2]);
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/events/{$this->event}/member-offer", $this->ada);
        self::assertSame(403, $this->code());
        $this->call('GET', "/api/events/{$this->event}/member-offer");
        self::assertSame(401, $this->code());
    }

    public function testTheCatalogueShowsOnlyPublishedEventsWithoutStaffOrRosterData(): void
    {
        $this->call('GET', '/api/member/events', $this->ada);
        self::assertSame(200, $this->code());
        self::assertSame(['verified' => true, 'items' => [], 'hasMore' => false], $this->json());

        $this->publish();
        $this->call('GET', '/api/member/events', $this->ada);
        self::assertCount(1, $this->json()['items']);
        $item = $this->json()['items'][0];
        self::assertSame(['id', 'eventId', 'title', 'description', 'startsAt', 'endsAt', 'timeZone', 'venueName', 'venueAddress', 'status', 'registrationOpen', 'waitlistEnabled', 'remainingPlaces', 'booking', 'coverImageUrl'], array_keys($item), 'a member sees event details and their own booking only');
        self::assertSame($this->occurrence, $item['id']);
        self::assertSame('Sunday service', $item['title']);
        self::assertTrue($item['registrationOpen']);
        self::assertNull($item['remainingPlaces']);
        self::assertNull($item['booking']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->call('GET', "/api/member/events/{$this->occurrence}", $this->ada);
        self::assertSame($item, $this->json());
        $this->connection->executeStatement("UPDATE event_registration_policies SET capacity = 3 WHERE occurrence_id = '{$this->occurrence}'");
        $this->call('GET', "/api/member/events/{$this->occurrence}", $this->ada);
        self::assertSame(3, $this->json()['remainingPlaces']);
    }

    public function testUnpublishedEventsAreInvisibleUntilTheMemberBooksAndStayVisibleAfterWithdrawal(): void
    {
        $this->call('GET', "/api/member/events/{$this->occurrence}", $this->ada);
        self::assertSame(404, $this->code());
        $this->book();
        self::assertSame(404, $this->code(), 'booking requires the event to be published');

        $this->publish();
        $this->book();
        self::assertSame(201, $this->code(), $this->message());
        $booking = $this->json()['id'];
        $this->publish(false, 1);

        $this->call('GET', '/api/member/events', $this->ada);
        self::assertSame([], $this->json()['items']);
        $this->call('GET', '/api/member/events?mine=true', $this->ada);
        self::assertSame($booking, $this->json()['items'][0]['booking']['id']);
        self::assertFalse($this->json()['items'][0]['registrationOpen'], 'withdrawn events cannot take new bookings');
        $this->call('GET', "/api/member/events/{$this->occurrence}", $this->ada);
        self::assertSame(200, $this->code());
    }

    public function testMembersBookCancelAndRebookOnlyForThemselves(): void
    {
        $this->publish();
        $this->book();
        self::assertSame(201, $this->code(), $this->message());
        $booking = $this->json()['id'];
        self::assertSame($this->adaPerson, $this->connection->fetchOne('SELECT person_id FROM event_registrations WHERE id = ?', [$booking]), 'the person comes from the verified identity');
        $this->book();
        self::assertSame(409, $this->code(), 'a second booking needs the versioned action');

        $this->call('POST', "/api/member/events/{$this->occurrence}/cancel", $this->ada, ['expectedVersion' => 5]);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/member/events/{$this->occurrence}/cancel", $this->ada, ['expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        self::assertSame('CANCELLED', $this->connection->fetchOne('SELECT status FROM event_registrations WHERE id = ?', [$booking]));
        self::assertSame(1, $this->rows('event_registration_history', "action = 'CANCELLED' AND reason = 'Cancelled by the member.'"));

        $this->book(version: 1);
        self::assertSame(201, $this->code(), $this->message());
        self::assertSame($booking, $this->json()['id']);

        [, $ben, $benPerson] = $this->member('ben', 'Ben');
        $this->call('POST', "/api/member/events/{$this->occurrence}/cancel", $ben, ['expectedVersion' => 0]);
        self::assertSame(404, $this->code(), 'a member cannot reach another member\'s booking');
        self::assertSame('CONFIRMED', $this->connection->fetchOne('SELECT status FROM event_registrations WHERE id = ?', [$booking]));
        $this->call('POST', "/api/member/events/{$this->occurrence}/registration", $ben, ['requestId' => Uuid::v4()->toRfc4122(), 'personId' => $this->adaPerson]);
        self::assertSame(201, $this->code(), 'an injected personId is ignored');
        self::assertSame($benPerson, $this->connection->fetchOne('SELECT person_id FROM event_registrations WHERE id = ?', [$this->json()['id']]));
    }

    public function testBookingChangesReachOnlyTheMembersOwnInbox(): void
    {
        $this->publish();
        $this->book();
        self::assertSame(201, $this->code(), $this->message());
        $this->call('GET', '/api/member/notifications', $this->ada);
        self::assertSame(1, $this->json()['unreadCount']);
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Your event booking was updated', $body);
        self::assertStringNotContainsString('personId', $body);
        self::assertStringNotContainsString('sourceKey', $body);
        self::assertSame('EVENTS', $this->json()['items'][0]['destination']);

        $this->call('POST', "/api/member/events/{$this->occurrence}/cancel", $this->ada, ['expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $this->call('GET', '/api/member/notifications', $this->ada);
        self::assertSame(2, $this->json()['unreadCount'], 'each booking revision is its own notice');

        [, $ben] = $this->member('ben', 'Ben');
        $this->call('GET', '/api/member/notifications', $ben);
        self::assertSame(0, $this->json()['unreadCount']);
    }

    public function testPeopleWithoutAVerifiedMemberLinkSeeNothingAndCannotBook(): void
    {
        $this->publish();
        $this->account('visitor');
        $visitor = $this->login('visitor');

        $this->call('GET', '/api/member/events', $visitor);
        self::assertSame(['verified' => false, 'items' => [], 'hasMore' => false], $this->json());
        $this->call('GET', "/api/member/events/{$this->occurrence}", $visitor);
        self::assertSame(404, $this->code());
        $this->book($visitor);
        self::assertContains($this->code(), [403, 404, 409]);
        self::assertSame(0, $this->rows('event_registrations'));
        $this->call('POST', "/api/member/events/{$this->occurrence}/attendance-pass", $visitor);
        self::assertContains($this->code(), [403, 404, 409]);
    }

    public function testPagingIsBoundedAndLookaheadReportsMore(): void
    {
        $this->publish();
        foreach (['limit=0', 'limit=101', 'offset=100001', 'limit=x'] as $query) {
            $this->call('GET', "/api/member/events?$query", $this->ada);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/member/events?limit=1', $this->ada);
        self::assertFalse($this->json()['hasMore']);
    }

    public function testThePassNeedsAConfirmedBookingAndTheAdmissionWindow(): void
    {
        $this->publish();
        $this->call('POST', "/api/member/events/{$this->occurrence}/attendance-pass", $this->ada);
        self::assertSame(404, $this->code(), 'no booking');
        $this->book();
        $this->call('POST', "/api/member/events/{$this->occurrence}/attendance-pass", $this->ada);
        self::assertSame(409, $this->code(), 'the window opens two hours before the start');

        $this->startsIn('1 hour');
        $this->call('POST', "/api/member/events/{$this->occurrence}/attendance-pass", $this->ada);
        self::assertSame(200, $this->code(), $this->message());
        self::assertMatchesRegularExpression('/\Adivinecommunityspace:event:v1:[A-Za-z0-9_-]{43}\z/', $this->json()['credential']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $stored = $this->connection->fetchAssociative('SELECT * FROM event_admission_passes');
        self::assertIsArray($stored);
        self::assertStringNotContainsString(substr($this->json()['credential'], 18), json_encode($stored, JSON_THROW_ON_ERROR), 'only the hash is stored');
        self::assertSame(64, \strlen((string) $stored['token_hash']));
        $expires = new DateTimeImmutable((string) $this->json()['expiresAt']);
        self::assertLessThanOrEqual(300, $expires->getTimestamp() - time());
        self::assertGreaterThan(200, $expires->getTimestamp() - time());
    }

    /** @return array{string, string} the credential and the booking id */
    private function pass(): array
    {
        $this->publish();
        $this->book();
        $booking = $this->json()['id'];
        $this->startsIn('1 hour');
        $this->call('POST', "/api/member/events/{$this->occurrence}/attendance-pass", $this->ada);
        self::assertSame(200, $this->code(), $this->message());

        return [(string) $this->json()['credential'], (string) $booking];
    }

    private function scan(string $credential, ?string $occurrence = null, ?string $token = null): void
    {
        $this->call('POST', '/api/events/occurrences/'.($occurrence ?? $this->occurrence).'/scan-member', $token ?? $this->door, ['requestId' => Uuid::v4()->toRfc4122(), 'credential' => $credential]);
    }

    public function testScanningAdmitsTheMemberOnceAndOnlyOnce(): void
    {
        [$credential, $booking] = $this->pass();

        $this->scan($credential);
        self::assertSame(201, $this->code(), $this->message());
        self::assertSame(1, $this->rows('event_attendance', "registration_id = '$booking' AND status = 'PRESENT'"));
        self::assertNotNull($this->connection->fetchOne('SELECT used_at FROM event_admission_passes'));

        $this->scan($credential);
        self::assertSame(409, $this->code(), 'a pass is single-use');
        self::assertSame(1, $this->rows('event_attendance'));
        $this->call('GET', '/api/member/events?mine=true', $this->ada);
        self::assertSame('PRESENT', $this->json()['items'][0]['booking']['attendance']);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidations(): iterable
    {
        yield 'booking changed' => ['UPDATE event_registrations SET version = version + 1'];
        yield 'schedule changed' => ['UPDATE event_occurrences SET schedule_revision = schedule_revision + 1'];
        yield 'member link changed' => ['UPDATE identity_member_links SET version = version + 1'];
        yield 'session ended' => ["UPDATE account_sessions SET expires_at = now() - interval '1 minute' WHERE account_id = (SELECT id FROM user_accounts WHERE username = 'ada')"];
        yield 'pass expired' => ["UPDATE event_admission_passes SET expires_at = now() - interval '1 second'"];
        yield 'account disabled' => ["UPDATE user_accounts SET status = 'disabled' WHERE username = 'ada'"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidations')]
    public function testAnyChangeToWhatThePassIsBoundToVoidsIt(string $sql): void
    {
        [$credential] = $this->pass();
        $this->connection->executeStatement($sql);

        $this->scan($credential);

        self::assertSame(409, $this->code(), $sql);
        self::assertSame(0, $this->rows('event_attendance'), $sql);
        self::assertNull($this->connection->fetchOne('SELECT used_at FROM event_admission_passes'), 'a rejected scan does not consume the pass');
    }

    public function testAPassBelongsToItsOwnOccurrenceAndNeedsTheScanPermissionAndAWellFormedCode(): void
    {
        [$credential] = $this->pass();
        $other = $this->event($this->draft((new DateTimeImmutable('+4 days'))->format('Y-m-d'), 1, $this->venue('Foyer', false)));
        $otherOccurrence = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences WHERE event_id = ?', [$other]);

        $this->scan($credential, $otherOccurrence);
        self::assertSame(409, $this->code(), 'wrong gathering');
        $this->scan('divinecommunityspace:event:v1:short');
        self::assertSame(400, $this->code());
        $this->scan('not a code at all');
        self::assertSame(400, $this->code());
        $unknown = 'divinecommunityspace:event:v1:'.str_repeat('a', 43);
        $this->scan($unknown);
        self::assertSame(404, $this->code(), 'unknown pass');
        self::assertStringNotContainsString(str_repeat('a', 43), (string) $this->client->getResponse()->getContent(), 'the credential is never echoed');
        $this->scan($credential, null, $this->reader);
        self::assertSame(403, $this->code());
        $this->scan($credential, null, $this->ada);
        self::assertSame(403, $this->code(), 'a member cannot scan');
        $this->call('POST', "/api/events/occurrences/{$this->occurrence}/scan-member", null, ['requestId' => Uuid::v4()->toRfc4122(), 'credential' => $credential]);
        self::assertSame(401, $this->code());
        self::assertSame(0, $this->rows('event_attendance'));
    }

    public function testMemberEndpointsRequireAuthentication(): void
    {
        foreach ([['GET', '/api/member/events'], ['GET', "/api/member/events/{$this->occurrence}"], ['POST', "/api/member/events/{$this->occurrence}/registration"], ['POST', "/api/member/events/{$this->occurrence}/cancel"], ['POST', "/api/member/events/{$this->occurrence}/attendance-pass"]] as [$method, $uri]) {
            $this->call($method, $uri, null, 'GET' === $method ? null : ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0]);
            self::assertSame(401, $this->code(), $uri);
        }
        $this->call('POST', "/api/member/events/{$this->occurrence}/registration", $this->ada, ['requestId' => 'x']);
        self::assertSame(400, $this->code());
        $this->call('POST', "/api/member/events/{$this->occurrence}/cancel", $this->ada, []);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/member/events/not-a-uuid', $this->ada);
        self::assertSame(400, $this->code());
    }
}
