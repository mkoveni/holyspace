<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final class SessionSeriesApiTest extends ChildrenApiTestCase
{
    private const string BASE = '/api/children/session-series';

    private string $room;
    private string $group;
    private string $source;
    private string $worker;
    private int $sourceVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $location, 'name' => 'Room 1', 'capacity' => 12]);
        $this->group = $this->create('/classes', ['programmeId' => $this->programme(), 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->source = $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => '2030-06-02T08:00:00Z', 'endsAt' => '2030-06-02T10:00:00Z', 'seatLimit' => 2]);

        $person = $this->person('Mabasa', $this->yearsAgo(30));
        $account = $this->accountWith('worker', ['children.station']);
        $this->call('POST', '/api/children/workers', $this->admin, $this->clearance($person, $account, '+20 years'));
        self::assertSame(204, $this->code());
        $this->worker = $person;
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $this->source, 'expectedVersion' => 0, 'workerId' => $person, 'role' => 'LEADER', 'present' => false]);
        self::assertSame(204, $this->code());
        $this->sourceVersion = $this->sessionVersion($this->source);
    }

    /** @return array<string, mixed> */
    private function clearance(string $person, string $account, string $until): array
    {
        $at = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($until)->format('Y-m-d\TH:i:s\Z');

        return ['personId' => $person, 'accountId' => $account, 'screenedUntil' => $at, 'trainedUntil' => $at, 'evidenceReference' => 'Police clearance'];
    }

    private function sessionVersion(string $id): int
    {
        $this->call('GET', "/api/children/sessions/$id", $this->admin);
        self::assertSame(200, $this->code(), $this->message());

        return (int) $this->json()['session']['version'];
    }

    /**
     * @param list<string> $skipped
     *
     * @return array<string, mixed>
     */
    private function draft(array $skipped = [], string $first = '2030-06-09'): array
    {
        return ['name' => 'Sunday welcome term', 'sourceSessionId' => $this->source, 'sourceVersion' => $this->sourceVersion, 'recurrence' => ['firstDate' => $first, 'startsAt' => '09:00', 'endsAt' => '10:00', 'intervalWeeks' => 1, 'occurrences' => 3, 'excludedDates' => $skipped]];
    }

    /** @return list<string> the series' session ids by date */
    private function sessionIds(string $series): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT session_id FROM children_series_sessions WHERE series_id = ? ORDER BY scheduled_date', [$series]);

        return $ids;
    }

    /** @param array<string, mixed> $draft */
    private function createSeries(array $draft): string
    {
        $this->call('POST', self::BASE, $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'draft' => $draft]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    public function testPreviewWritesNothingAndCreationCopiesAnAbsentTeamWithRetrySafety(): void
    {
        $draft = $this->draft(['2030-06-16']);
        $sessions = $this->rows('children_sessions');

        $this->call('POST', self::BASE.'/preview', $this->admin, $draft);
        self::assertSame(200, $this->code(), $this->message());
        $preview = $this->json();
        self::assertSame(['Juniors', 'Room 1', 'Africa/Johannesburg', 2], [$preview['className'], $preview['roomName'], $preview['timeZone'], $preview['seatLimit']]);
        self::assertSame([$this->worker, 'LEADER'], [$preview['team'][0]['workerId'], $preview['team'][0]['role']]);
        self::assertStringContainsString('Mabasa', $preview['team'][0]['name']);
        self::assertSame('2030-06-09T07:00:00Z', $preview['occurrences'][0]['slot']['startsAt']);
        self::assertTrue($preview['occurrences'][1]['slot']['excluded']);
        self::assertSame([], $preview['occurrences'][0]['conflicts']);
        self::assertStringNotContainsString('evidenceReference', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('screenedUntil', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->rows('children_session_series'));
        self::assertSame($sessions, $this->rows('children_sessions'));

        $request = ['requestId' => Uuid::v4()->toRfc4122(), 'draft' => $draft];
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(201, $this->code(), $this->message());
        $id = (string) $this->json()['id'];
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(201, $this->code());
        self::assertSame($id, $this->json()['id'], 'a retry returns the same series');
        self::assertSame($sessions + 2, $this->rows('children_sessions'));
        self::assertSame(1, $this->rows('children_session_series'));
        self::assertSame(2, $this->rows('children_series_sessions'));

        $ids = $this->sessionIds($id);
        foreach ($ids as $sessionId) {
            self::assertSame(1, $this->rows('children_session_staff', "session_id = '$sessionId' AND present = FALSE AND worker_id = '{$this->worker}'"));
        }
        self::assertSame(2, $this->rows('children_audit', "action = 'session.created' AND reason = 'series:$id'"));
        self::assertSame(1, $this->rows('children_audit', "action = 'session.series.created' AND target_id = '$id'"));

        $this->call('GET', self::BASE."/$id", $this->admin);
        self::assertSame(200, $this->code());
        self::assertSame([2, 2, 0, false, 0], [$this->json()['series']['totalSessions'], $this->json()['series']['plannedSessions'], $this->json()['series']['cancelledSessions'], $this->json()['series']['stopped'], $this->json()['series']['version']]);
        self::assertSame(['2030-06-09', '2030-06-23'], array_column($this->json()['sessions'], 'date'));
        $this->call('GET', self::BASE.'?limit=1', $this->admin);
        self::assertSame([$id, 1], [$this->json()['series'][0]['id'], $this->json()['total']]);

        $request['draft'] = $this->draft();
        $this->call('POST', self::BASE, $this->admin, $request);
        self::assertSame(409, $this->code(), 'the same request id cannot carry a different plan');
    }

    public function testANewClashAfterPreviewRejectsTheWholeSeriesWithoutPartialSessionsOrAudits(): void
    {
        $draft = $this->draft();
        $this->call('POST', self::BASE.'/preview', $this->admin, $draft);
        self::assertSame(200, $this->code());
        $this->create('/sessions', ['classId' => $this->group, 'roomId' => $this->room, 'startsAt' => '2030-06-16T07:00:00Z', 'endsAt' => '2030-06-16T08:00:00Z', 'seatLimit' => 2]);
        $sessions = $this->rows('children_sessions');
        $audits = $this->rows('children_audit');

        $this->call('POST', self::BASE.'/preview', $this->admin, $draft);
        self::assertStringContainsString('already booked', json_encode($this->json(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->call('POST', self::BASE, $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'draft' => $draft]);

        self::assertSame(409, $this->code());
        self::assertSame($sessions, $this->rows('children_sessions'));
        self::assertSame($audits, $this->rows('children_audit'));
        self::assertSame(0, $this->rows('children_session_series'));
    }

    public function testCancellationPreservesAnOpenSessionAndEnforcesSeriesVersions(): void
    {
        $id = $this->createSeries($this->draft());
        $active = $this->sessionIds($id)[0];
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $active, 'expectedVersion' => 0, 'workerId' => $this->worker, 'role' => 'LEADER', 'present' => true]);
        self::assertSame(204, $this->code());
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $active, 'expectedVersion' => $this->sessionVersion($active), 'status' => 'OPEN', 'reason' => 'Preparing the room']);
        self::assertSame(204, $this->code());

        $this->call('POST', self::BASE."/$id/cancel", $this->admin, ['expectedVersion' => 9, 'reason' => 'Term change']);
        self::assertSame(409, $this->code());
        $this->call('POST', self::BASE."/$id/cancel", $this->admin, ['expectedVersion' => 0, 'reason' => 'Term change']);
        self::assertSame(204, $this->code());

        self::assertSame('OPEN', $this->connection->fetchOne('SELECT status FROM children_sessions WHERE id = ?', [$active]));
        $this->call('GET', self::BASE."/$id", $this->admin);
        self::assertSame([2, true, 1], [$this->json()['series']['cancelledSessions'], $this->json()['series']['stopped'], $this->json()['series']['version']]);
        self::assertSame(1, $this->rows('children_audit', "action = 'session.series.cancelled' AND target_id = '$id' AND reason = 'Term change'"));
        self::assertSame(2, $this->rows('children_audit', "action = 'session.status.changed' AND reason = 'Term change'"));
        $this->call('POST', self::BASE."/$id/cancel", $this->admin, ['expectedVersion' => 1, 'reason' => 'Again']);
        self::assertSame(409, $this->code());
    }

    public function testClearanceWarningsAllowPlanningButDoNotOpenFutureSessions(): void
    {
        $this->call('PUT', '/api/children/workers', $this->admin, ['personId' => $this->worker, 'expectedVersion' => 0, 'screenedUntil' => '2030-06-01T00:00:00Z', 'trainedUntil' => '2030-06-01T00:00:00Z', 'evidenceReference' => 'renewal pending', 'revoked' => false]);
        self::assertSame(204, $this->code());

        $this->call('POST', self::BASE.'/preview', $this->admin, $this->draft());
        self::assertSame(200, $this->code());
        self::assertStringContainsString('review identity, account or screening/training', json_encode($this->json(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $future = $this->sessionIds($this->createSeries($this->draft()))[0];
        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $future, 'expectedVersion' => 0, 'status' => 'OPEN', 'reason' => 'Try opening']);
        self::assertSame(409, $this->code());
    }

    public function testInvalidPatternsAndStaleSourcesAreRejected(): void
    {
        $this->call('POST', self::BASE.'/preview', $this->admin, ['sourceVersion' => 0] + $this->draft());
        self::assertSame(409, $this->code(), 'the source session has moved on');
        $invalid = $this->draft();
        $invalid['recurrence']['occurrences'] = 53;
        $this->call('POST', self::BASE.'/preview', $this->admin, $invalid);
        self::assertSame(400, $this->code());
        foreach ([
            ['name' => ''] + $this->draft(),
            ['sourceSessionId' => 'nope'] + $this->draft(),
            ['recurrence' => null] + $this->draft(),
            ['recurrence' => ['firstDate' => 'soon'] + $this->draft()['recurrence']] + $this->draft(),
            ['recurrence' => ['startsAt' => '9am'] + $this->draft()['recurrence']] + $this->draft(),
            ['recurrence' => ['excludedDates' => ['2030-06-10']] + $this->draft()['recurrence']] + $this->draft(),
        ] as $body) {
            $this->call('POST', self::BASE.'/preview', $this->admin, $body);
            self::assertSame(400, $this->code(), json_encode($body, JSON_THROW_ON_ERROR));
        }
        $this->call('POST', self::BASE, $this->admin, ['requestId' => 'nope', 'draft' => $this->draft()]);
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE, $this->admin, ['draft' => $this->draft()]);
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE.'/preview', $this->admin, ['sourceSessionId' => Uuid::v4()->toRfc4122()] + $this->draft());
        self::assertSame(404, $this->code());
        $this->call('POST', self::BASE.'/'.Uuid::v4()->toRfc4122().'/cancel', $this->admin, ['expectedVersion' => 0, 'reason' => 'Change']);
        self::assertSame(404, $this->code());
        $this->call('POST', self::BASE.'/'.Uuid::v4()->toRfc4122().'/cancel', $this->admin, ['reason' => 'Change']);
        self::assertSame(400, $this->code(), 'a missing expected version is not zero');
        $this->call('GET', self::BASE.'/not-a-uuid', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'?limit=0', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
    }

    public function testReadersCanReadButNotPlanOrCancelAndAnonymousCallersAreRejected(): void
    {
        $id = $this->createSeries($this->draft());

        $this->call('GET', self::BASE, $this->reader);
        self::assertSame(200, $this->code());
        $this->call('GET', self::BASE."/$id", $this->reader);
        self::assertSame(200, $this->code());
        $this->call('POST', self::BASE.'/preview', $this->reader, $this->draft());
        self::assertSame(403, $this->code());
        $this->call('POST', self::BASE, $this->reader, ['requestId' => Uuid::v4()->toRfc4122(), 'draft' => $this->draft()]);
        self::assertSame(403, $this->code());
        $this->call('POST', self::BASE."/$id/cancel", $this->reader, ['expectedVersion' => 0, 'reason' => 'Change']);
        self::assertSame(403, $this->code());

        foreach ([['GET', self::BASE], ['GET', self::BASE."/$id"], ['POST', self::BASE.'/preview'], ['POST', self::BASE], ['POST', self::BASE."/$id/cancel"]] as [$method, $uri]) {
            $this->call($method, $uri, null, []);
            self::assertSame(401, $this->code(), "$method $uri");
        }
        self::assertSame(0, $this->rows('children_series_sessions', "series_id = '$id' AND session_id IN (SELECT id FROM children_sessions WHERE status = 'CANCELLED')"));
    }
}
