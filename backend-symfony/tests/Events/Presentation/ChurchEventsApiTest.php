<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class ChurchEventsApiTest extends EventsApiTestCase
{
    public function testPreviewListsUtcWindowsAndAHashWithoutWriting(): void
    {
        $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => $this->draft()]);

        self::assertSame(200, $this->code());
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $this->json()['hash']);
        self::assertSame($this->venue, $this->json()['venue']['id']);
        self::assertCount(3, $this->json()['dates']);
        self::assertSame($this->nextYear(), $this->json()['dates'][0]['originDate']);
        self::assertMatchesRegularExpression('/T08:00:00Z\z/', $this->json()['dates'][0]['startsAt']);
        self::assertSame(0, $this->rows('event_definitions'));
        self::assertSame(0, $this->rows('event_occurrences'));
    }

    public function testCreatingWritesTheEventSeriesOccurrencesPoliciesHistoryAndAudit(): void
    {
        $id = $this->event();

        self::assertSame(1, $this->rows('event_definitions', "id = '$id'"));
        self::assertSame(1, $this->rows('event_series', "event_id = '$id'"));
        self::assertSame(3, $this->rows('event_occurrences', "event_id = '$id' AND status = 'SCHEDULED' AND NOT overridden"));
        self::assertSame(3, $this->rows('event_registration_policies', 'NOT open AND capacity IS NULL AND waitlist_enabled AND next_position = 0'));
        self::assertSame(3, $this->rows('event_occurrence_history', "action = 'SCHEDULED'"));
        self::assertSame(1, $this->rows('event_audit', "action = 'event.created' AND target_id = '$id'"));
        self::assertSame(1, $this->rows('event_requests', "kind = 'CREATE_EVENT' AND target_id = '$id'"));
        self::assertSame('Main hall', $this->connection->fetchOne('SELECT DISTINCT venue_name FROM event_occurrences'));
    }

    public function testRepeatingARequestReturnsTheSameEventAndADifferentBodyConflicts(): void
    {
        $request = Uuid::v4()->toRfc4122();
        $body = $this->body($this->draft(), $request);
        $this->call('POST', '/api/events', $this->manager, $body);
        $first = (string) $this->json()['id'];
        $this->call('POST', '/api/events', $this->manager, $body);
        self::assertSame(201, $this->code());
        self::assertSame($first, $this->json()['id']);
        self::assertSame(1, $this->rows('event_definitions'));

        $changed = $body;
        $changed['title'] = 'Different';
        $this->call('POST', '/api/events', $this->manager, $changed);
        self::assertSame(409, $this->code());
    }

    public function testAStalePreviewHashConflictsAndRollsBackEverything(): void
    {
        $draft = $this->draft();
        $this->call('POST', '/api/events', $this->manager, $this->body($draft, Uuid::v4()->toRfc4122(), str_repeat('0', 64)));

        self::assertSame(409, $this->code());
        foreach (['event_definitions', 'event_series', 'event_occurrences', 'event_registration_policies', 'event_requests'] as $table) {
            self::assertSame(0, $this->rows($table), $table);
        }
    }

    public function testAnExclusiveVenueRejectsOverlapsButASharedOneAllowsThem(): void
    {
        $this->event();
        $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => $this->draft()]);
        self::assertSame(409, $this->code());

        $shared = $this->venue('Foyer', exclusive: false);
        $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => $this->draft(venue: $shared)]);
        self::assertSame(200, $this->code());
    }

    public function testInvalidSchedulesAreRejected(): void
    {
        $cases = [
            'past' => $this->draft('2020-01-01'),
            'monthly day 31' => $this->draft($this->nextYear('01-31'), 2, null, 'MONTHLY'),
            'dst gap' => ['timeZone' => 'Europe/London', 'firstDate' => (new DateTimeImmutable('last sunday of march '.((int) (new DateTimeImmutable())->format('Y') + 1)))->format('Y-m-d'), 'startsAt' => '01:30', 'frequency' => 'ONCE', 'count' => 1] + $this->draft(),
            'zone' => ['timeZone' => 'Nowhere/City'] + $this->draft(),
            'count' => $this->draft(count: 105),
        ];
        foreach ($cases as $name => $draft) {
            $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => $draft]);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => ['firstDate' => 'nope'] + $this->draft()]);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events/schedule/preview', $this->reader, ['draft' => ['venueId' => Uuid::v4()->toRfc4122()] + $this->draft()]);
        self::assertSame(404, $this->code());
        $this->call('POST', '/api/events/schedule/preview', $this->reader, []);
        self::assertSame(400, $this->code());
    }

    public function testEventsAreListedSearchedAndShownWithTheirSeries(): void
    {
        $id = $this->event();

        $this->call('GET', '/api/events?search=SUNDAY', $this->reader);
        self::assertSame(1, $this->json()['total']);
        self::assertSame('Sunday service', $this->json()['events'][0]['title']);
        $this->call('GET', '/api/events?search=%25', $this->reader);
        self::assertSame(0, $this->json()['total'], 'percent is literal');
        foreach (['limit=0', 'limit=101', 'offset=100001', 'limit=x'] as $query) {
            $this->call('GET', "/api/events?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }

        $this->call('GET', "/api/events/$id", $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame($id, $this->json()['event']['id']);
        self::assertSame(1, $this->json()['totalSeries']);
        $series = $this->json()['series'][0];
        self::assertSame('10:00:00', $series['startsAt']);
        self::assertSame('11:30:00', $series['endsAt']);
        self::assertSame($this->nextYear(), $series['firstDate']);
        self::assertSame(3, $series['count']);

        $this->call('GET', '/api/events/'.Uuid::v4()->toRfc4122(), $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/events/not-a-uuid', $this->reader);
        self::assertSame(400, $this->code());
    }

    public function testUpdatingNeedsTheVersionAReasonAndNoFutureOccurrencesToArchive(): void
    {
        $id = $this->event();
        $update = ['expectedVersion' => 0, 'title' => 'Renamed', 'type' => 'MEETING', 'description' => 'd', 'active' => true, 'reason' => 'Rename'];

        $this->call('PUT', "/api/events/$id", $this->manager, ['reason' => ' '] + $update);
        self::assertSame(400, $this->code());
        $this->call('PUT', "/api/events/$id", $this->manager, ['expectedVersion' => 9] + $update);
        self::assertSame(409, $this->code());
        $this->call('PUT', "/api/events/$id", $this->manager, ['active' => false] + $update);
        self::assertSame(409, $this->code(), 'future occurrences block archiving');
        $this->call('PUT', "/api/events/$id", $this->manager, ['type' => 'PARTY'] + $update);
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('event_audit', "action = 'event.updated'"));

        $this->call('PUT', "/api/events/$id", $this->manager, $update);
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('event_audit', "action = 'event.updated' AND reason = 'Rename'"));
        $this->call('PUT', '/api/events/'.Uuid::v4()->toRfc4122(), $this->manager, $update);
        self::assertSame(404, $this->code());

        $this->connection->executeStatement("UPDATE event_occurrences SET status = 'CANCELLED'");
        $this->call('PUT', "/api/events/$id", $this->manager, ['expectedVersion' => 1, 'active' => false] + $update);
        self::assertSame(204, $this->code());
        $this->call('GET', '/api/events', $this->reader);
        self::assertSame(0, $this->json()['total']);
        $this->call('GET', '/api/events?includeArchived=true', $this->reader);
        self::assertSame(1, $this->json()['total']);
    }

    public function testAddingASeriesIsVersionCheckedIdempotentAndBumpsTheEvent(): void
    {
        $id = $this->event();
        $shared = $this->venue('Foyer', exclusive: false);
        $draft = $this->draft(count: 2, venue: $shared);
        $request = Uuid::v4()->toRfc4122();
        $body = ['requestId' => $request, 'expectedVersion' => 0, 'schedule' => $draft, 'previewHash' => $this->hash($draft)];

        $this->call('POST', "/api/events/$id/series", $this->manager, ['expectedVersion' => 5] + $body);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/events/$id/series", $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());
        $series = $this->json()['id'];
        $this->call('POST', "/api/events/$id/series", $this->manager, $body);
        self::assertSame($series, $this->json()['id'], 'a repeat returns the original series');

        self::assertSame(2, $this->rows('event_series'));
        self::assertSame(5, $this->rows('event_occurrences'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT version FROM event_definitions WHERE id = ?', [$id]));
        self::assertSame(1, $this->rows('event_audit', "action = 'series.created'"));

        $this->call('POST', '/api/events/'.Uuid::v4()->toRfc4122().'/series', $this->manager, $body);
        self::assertSame(404, $this->code());
    }

    public function testPermissionsAreEnforced(): void
    {
        $id = $this->event();
        foreach ([['GET', '/api/events'], ['GET', "/api/events/$id"], ['POST', '/api/events/schedule/preview'], ['POST', '/api/events'], ['PUT', "/api/events/$id"], ['POST', "/api/events/$id/series"]] as [$method, $uri]) {
            $this->call($method, $uri);
            self::assertSame(401, $this->code(), "$method $uri");
        }
        $this->call('POST', '/api/events', $this->reader, $this->body($this->draft(), Uuid::v4()->toRfc4122(), 'x'));
        self::assertSame(403, $this->code());
        $this->call('PUT', "/api/events/$id", $this->reader, ['expectedVersion' => 0, 'reason' => 'x']);
        self::assertSame(403, $this->code());
        $this->call('POST', "/api/events/$id/series", $this->reader, ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => 0, 'schedule' => $this->draft()]);
        self::assertSame(403, $this->code());
        self::assertSame(1, $this->rows('event_definitions'));
    }

    public function testMalformedCreateBodiesAreRejected(): void
    {
        $this->call('POST', '/api/events', $this->manager, ['title' => 'x']);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events', $this->manager, ['requestId' => 'nope'] + $this->body($this->draft(), 'x'));
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events', $this->manager, ['type' => 'PARTY'] + $this->body($this->draft(), Uuid::v4()->toRfc4122()));
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events', $this->manager, ['title' => ' '] + $this->body($this->draft(), Uuid::v4()->toRfc4122()));
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('event_definitions'));
    }
}
