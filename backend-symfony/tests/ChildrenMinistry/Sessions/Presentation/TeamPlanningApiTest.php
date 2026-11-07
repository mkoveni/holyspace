<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Presentation;

use App\Tests\ChildrenMinistry\Participation\Presentation\ChildrenApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final class TeamPlanningApiTest extends ChildrenApiTestCase
{
    private const string BASE = '/api/children/team-planning';

    private string $location;
    private string $room;
    private string $group;
    private string $programme;
    private string $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->location = $this->create('/locations', ['name' => 'Main', 'timeZone' => 'Africa/Johannesburg']);
        $this->room = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room 1', 'capacity' => 12]);
        $this->programme = $this->programme();
        $this->group = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Juniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $this->worker = $this->registerWorker('Mabasa');
    }

    private function registerWorker(string $name): string
    {
        $person = $this->person($name, $this->yearsAgo(30));
        $account = $this->accountWith(strtolower($name), ['children.station']);
        $until = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+20 years')->format('Y-m-d\TH:i:s\Z');
        $this->call('POST', '/api/children/workers', $this->admin, ['personId' => $person, 'accountId' => $account, 'screenedUntil' => $until, 'trainedUntil' => $until, 'evidenceReference' => 'Verified']);
        self::assertSame(204, $this->code());

        return $person;
    }

    /** A session at the given local time, $days from today, lasting $minutes. */
    private function session(int $days, string $time = '09:00', int $minutes = 60, ?string $room = null, ?string $class = null): string
    {
        $start = new DateTimeImmutable($this->today("+$days days").' '.$time, new DateTimeZone('Africa/Johannesburg'));
        $utc = new DateTimeZone('UTC');

        return $this->create('/sessions', [
            'classId' => $class ?? $this->group,
            'roomId' => $room ?? $this->room,
            'startsAt' => $start->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'endsAt' => $start->modify("+$minutes minutes")->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'seatLimit' => 2,
        ]);
    }

    /**
     * @param list<array{string, int}> $targets
     *
     * @return array<string, mixed>
     */
    private function draft(array $targets, ?string $worker = null, ?string $replace = null, string $role = 'LEADER'): array
    {
        $draft = ['targets' => array_map(static fn (array $t): array => ['sessionId' => $t[0], 'version' => $t[1]], $targets), 'workerId' => $worker ?? $this->worker, 'role' => $role];

        return null === $replace ? $draft : $draft + ['replaceWorkerId' => $replace];
    }

    private function listing(string $extra = ''): string
    {
        return self::BASE.'?from='.$this->today().'&until='.$this->today('+30 days').$extra;
    }

    /** @param array<string, mixed> $draft */
    private function applyPlan(array $draft, string $reason = 'Sunday team agreed'): void
    {
        $this->call('POST', self::BASE.'/apply', $this->admin, ['draft' => $draft, 'reason' => $reason]);
    }

    private function staffCount(string $where = 'TRUE'): int
    {
        return $this->rows('children_session_staff', $where);
    }

    private function version(string $session): int
    {
        return (int) $this->connection->fetchOne('SELECT version FROM children_sessions WHERE id = ?', [$session]);
    }

    public function testPreviewIsReadOnlyAndBulkApplyPlansAnAbsentTeamWithGlobalReadinessCounts(): void
    {
        $first = $this->session(7);
        $second = $this->session(14);
        $draft = $this->draft([[$first, 0], [$second, 0]]);
        $audits = $this->rows('children_audit');

        $this->call('POST', self::BASE.'/preview', $this->admin, $draft);
        self::assertSame(200, $this->code(), $this->message());
        $preview = $this->json();
        self::assertSame([], $preview['blockers']);
        self::assertSame([2, true, false], [$preview['sessions'][0]['plannedCapacity'], $preview['sessions'][0]['ready'], $preview['sessions'][0]['team'][0]['present']]);
        self::assertSame($audits, $this->rows('children_audit'));
        self::assertSame(0, $this->staffCount());

        $this->applyPlan($draft);
        self::assertSame(204, $this->code());
        self::assertSame(2, $this->staffCount("session_id IN ('$first', '$second') AND present = FALSE AND worker_id = '{$this->worker}'"));
        self::assertSame([1, 1], [$this->version($first), $this->version($second)]);
        self::assertSame(2, $this->rows('children_audit', "action = 'session.team.planned' AND reason = 'Sunday team agreed'"));

        $this->call('GET', $this->listing('&limit=1&state=READY'), $this->admin);
        self::assertSame(200, $this->code(), $this->message());
        self::assertSame([2, 2, 2, 1], [$this->json()['total'], $this->json()['summary']['ready'], $this->json()['summary']['sessions'], count($this->json()['sessions'])]);
        self::assertSame('Africa/Johannesburg', $this->json()['timeZone']);
        $this->call('GET', $this->listing('&search=Mabasa&offset=1&limit=1'), $this->admin);
        self::assertSame(2, $this->json()['total']);
        self::assertSame('Mabasa Mabasa', $this->json()['sessions'][0]['team'][0]['name']);
        $this->call('GET', $this->listing('&state=ATTENTION'), $this->admin);
        self::assertSame([0, 2], [$this->json()['total'], $this->json()['summary']['ready']]);

        $this->applyPlan($draft, 'Retry stale versions');
        self::assertSame(409, $this->code());
        self::assertSame(2, $this->rows('children_audit', "action = 'session.team.planned'"));
    }

    public function testAStaleSelectionRollsBackEverythingAndClearanceWarningsStillAllowAdvancePlanning(): void
    {
        $first = $this->session(7);
        $second = $this->session(14);
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $second, 'expectedVersion' => 0, 'workerId' => $this->worker, 'role' => 'ASSISTANT', 'present' => false]);
        self::assertSame(204, $this->code());
        $audits = $this->rows('children_audit');

        $this->applyPlan($this->draft([[$first, 0], [$second, 0]]), 'Change team');
        self::assertSame(409, $this->code(), 'the second session moved on');
        self::assertSame(0, $this->staffCount("session_id = '$first'"));
        self::assertSame($audits, $this->rows('children_audit'));

        $soon = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 days')->format('Y-m-d\TH:i:s\Z');
        $this->call('PUT', '/api/children/workers', $this->admin, ['personId' => $this->worker, 'expectedVersion' => 0, 'screenedUntil' => $soon, 'trainedUntil' => $soon, 'evidenceReference' => 'Renewal scheduled', 'revoked' => false]);
        self::assertSame(204, $this->code());
        $this->call('POST', self::BASE.'/preview', $this->admin, $this->draft([[$first, 0]]));
        self::assertSame(200, $this->code());
        self::assertSame([false, []], [$this->json()['sessions'][0]['ready'], $this->json()['blockers']]);
        self::assertStringContainsString('Screening or training', json_encode($this->json(), JSON_THROW_ON_ERROR));

        $this->applyPlan($this->draft([[$first, 0]]), 'Renewal before service');
        self::assertSame(204, $this->code());
        $this->call('GET', $this->listing('&state=ATTENTION'), $this->admin);
        self::assertSame([2, 2], [$this->json()['total'], $this->json()['summary']['shortCapacity']]);
    }

    public function testAbsentAssignmentsBlockClashesButAdjacentAndCancelledBookingsDoNot(): void
    {
        $first = $this->session(7);
        $otherRoom = $this->create('/rooms', ['locationId' => $this->location, 'name' => 'Room B', 'capacity' => 10]);
        $otherClass = $this->create('/classes', ['programmeId' => $this->programme, 'name' => 'Seniors', 'minAgeMonths' => 60, 'maxAgeMonths' => 156]);
        $clash = $this->session(7, '09:30', 60, $otherRoom, $otherClass);
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $clash, 'expectedVersion' => 0, 'workerId' => $this->worker, 'role' => 'LEADER', 'present' => false]);
        self::assertSame(204, $this->code());

        $this->call('POST', self::BASE.'/preview', $this->admin, $this->draft([[$first, 0]]));
        self::assertStringContainsString('overlapping assignment', json_encode($this->json(), JSON_THROW_ON_ERROR));
        self::assertNotSame([], $this->json()['blockers']);
        $this->applyPlan($this->draft([[$first, 0]]), 'Clash after preview');
        self::assertSame(409, $this->code());

        $this->call('PUT', '/api/children/sessions/status', $this->admin, ['sessionId' => $clash, 'expectedVersion' => 1, 'status' => 'CANCELLED', 'reason' => 'Service cancelled']);
        self::assertSame(204, $this->code());
        $adjacent = $this->session(7, '10:00', 90, $otherRoom, $otherClass);
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $adjacent, 'expectedVersion' => 0, 'workerId' => $this->worker, 'role' => 'LEADER', 'present' => false]);
        self::assertSame(204, $this->code());

        $this->call('POST', self::BASE.'/preview', $this->admin, $this->draft([[$first, 0]]));
        self::assertSame([], $this->json()['blockers'], 'a cancelled and an adjacent booking are not clashes');
        $both = $this->draft([[$first, 0], [$adjacent, 1]]);
        $this->call('POST', self::BASE.'/preview', $this->admin, $both);
        self::assertSame([], $this->json()['blockers']);
        $this->applyPlan($both, 'Adjacent services');
        self::assertSame(204, $this->code());
    }

    public function testReplacementRequiresMembershipAndNeverRewritesConfirmedPresence(): void
    {
        $first = $this->session(7);
        $replacement = $this->registerWorker('Replacement');
        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $first, 'expectedVersion' => 0, 'workerId' => $this->worker, 'role' => 'LEADER', 'present' => false]);
        self::assertSame(204, $this->code());

        $this->applyPlan($this->draft([[$first, 1]], $replacement, $this->worker), 'Cover confirmed');
        self::assertSame(204, $this->code());
        self::assertSame(0, $this->staffCount("session_id = '$first' AND worker_id = '{$this->worker}'"));
        self::assertSame(1, $this->staffCount("session_id = '$first' AND worker_id = '$replacement' AND present = FALSE"));

        $this->call('PUT', '/api/children/sessions/staff', $this->admin, ['sessionId' => $first, 'expectedVersion' => 2, 'workerId' => $replacement, 'role' => 'LEADER', 'present' => true]);
        self::assertSame(204, $this->code());
        $present = $this->draft([[$first, 3]], $this->worker, $replacement);
        $this->call('POST', self::BASE.'/preview', $this->admin, $present);
        self::assertStringContainsString('Confirmed presence', json_encode($this->json(), JSON_THROW_ON_ERROR));
        $this->applyPlan($present, 'Should remain present');
        self::assertSame(409, $this->code());
        self::assertSame(1, $this->staffCount("session_id = '$first' AND worker_id = '$replacement' AND present = TRUE"));
    }

    public function testBoundsAndWorkerSearchAreExplicit(): void
    {
        $this->call('GET', self::BASE.'?from='.$this->today().'&until='.$this->today('+90 days'), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'?from='.$this->today('+2 days').'&until='.$this->today(), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', self::BASE.'?from='.$this->today(), $this->admin);
        self::assertSame(400, $this->code(), 'both dates are required');
        $this->call('GET', self::BASE.'?from=soon&until='.$this->today(), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', $this->listing('&state=EVERYTHING'), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', $this->listing('&limit=101'), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', $this->listing('&programmeId=nope'), $this->admin);
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE.'/preview', $this->admin, ['targets' => [], 'workerId' => $this->worker, 'role' => 'LEADER']);
        self::assertSame(400, $this->code());

        $this->call('GET', self::BASE.'/workers?search=Mabasa', $this->admin);
        self::assertSame(200, $this->code());
        $body = (string) $this->client->getResponse()->getContent();
        self::assertSame(['Mabasa Mabasa', false], [$this->json()['workers'][0]['name'], $this->json()['workers'][0]['revoked']]);
        self::assertStringNotContainsString('evidenceReference', $body);
        self::assertStringNotContainsString('accountId', $body);
        $this->call('GET', self::BASE.'/workers?search=Notthere', $this->admin);
        self::assertSame(0, $this->json()['total']);
        $this->call('GET', self::BASE.'/workers', $this->admin);
        self::assertSame(1, $this->json()['total']);
    }

    public function testMalformedDraftsAreRejectedWithoutTouchingAnything(): void
    {
        $first = $this->session(7);
        $good = $this->draft([[$first, 0]]);
        foreach ([
            ['targets' => [null]] + $good,
            ['targets' => [['sessionId' => 'nope', 'version' => 0]]] + $good,
            ['targets' => [['sessionId' => $first, 'version' => -1]]] + $good,
            ['targets' => [['sessionId' => $first, 'version' => 0], ['sessionId' => $first, 'version' => 0]]] + $good,
            ['role' => 'BOSS'] + $good,
            ['role' => null] + $good,
            ['workerId' => 'nope'] + $good,
            ['replaceWorkerId' => $this->worker] + $good,
            ['targets' => array_fill(0, 26, ['sessionId' => $first, 'version' => 0])] + $good,
        ] as $body) {
            $this->call('POST', self::BASE.'/preview', $this->admin, $body);
            self::assertSame(400, $this->code(), json_encode($body, JSON_THROW_ON_ERROR));
        }
        $this->applyPlan($good, '');
        self::assertSame(400, $this->code());
        $this->applyPlan($good, str_repeat('x', 501));
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE.'/apply', $this->admin, ['reason' => 'No draft']);
        self::assertSame(400, $this->code());
        $this->call('POST', self::BASE.'/preview', $this->admin, $this->draft([[Uuid::v4()->toRfc4122(), 0]]));
        self::assertSame(404, $this->code());
        $this->call('POST', self::BASE.'/preview', $this->admin, $this->draft([[$first, 0]], Uuid::v4()->toRfc4122()));
        self::assertSame(404, $this->code());
        self::assertSame(0, $this->staffCount());
        self::assertSame(0, $this->rows('children_audit', "action = 'session.team.planned'"));
    }

    public function testReadersAndAnonymousCallersAreRejectedOnEveryRoute(): void
    {
        $first = $this->session(7);
        $draft = $this->draft([[$first, 0]]);
        $routes = [
            ['GET', $this->listing(), []],
            ['GET', self::BASE.'/workers', []],
            ['POST', self::BASE.'/preview', $draft],
            ['POST', self::BASE.'/apply', ['draft' => $draft, 'reason' => 'Denied']],
        ];
        foreach ($routes as [$method, $uri, $body]) {
            $this->call($method, $uri, $this->reader, [] === $body ? null : $body);
            self::assertSame(403, $this->code(), "$method $uri as a reader");
            $this->call($method, $uri, null, [] === $body ? null : $body);
            self::assertSame(401, $this->code(), "$method $uri anonymously");
        }
        self::assertSame(0, $this->staffCount());
    }
}
