<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Events\Infrastructure\Persistence\DbalOccurrenceStore;
use App\Events\Infrastructure\PublishedEventActivities;
use App\Volunteering\Application\ActivityRegistry;
use App\Volunteering\Application\Contract\ServingActivity;
use App\Volunteering\Application\Contract\ShiftView;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final class EventOccurrencesApiTest extends EventsApiTestCase
{
    /** @var list<string> */
    private array $ids = [];
    private string $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->event();
        $this->ids = $this->connection->fetchFirstColumn('SELECT id FROM event_occurrences ORDER BY starts_at');
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function change(string $action, string $scope = 'THIS', ?string $occurrence = null, int $version = 0, array $extra = []): array
    {
        $reschedule = 'RESCHEDULE' === $action;

        return array_replace(
            ['occurrenceId' => $occurrence ?? $this->ids[0], 'expectedVersion' => $version, 'action' => $action, 'scope' => $scope]
            + ($reschedule ? ['date' => $this->nextYear('03-16'), 'startsAt' => '12:00', 'endsAt' => '13:00', 'endDayOffset' => 0, 'venueId' => $this->venue] : []),
            $extra,
        );
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return array<int|string, mixed>
     */
    private function preview(array $draft): array
    {
        $this->call('POST', '/api/events/occurrences/preview', $this->reader, ['draft' => $draft]);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json();
    }

    /** @param array<string, mixed> $draft */
    private function apply(string $path, array $draft, ?string $hash = null, string $reason = 'Because'): void
    {
        $this->call('POST', "/api/events/occurrences/$path", $this->manager, ['draft' => $draft, 'reviewHash' => $hash ?? $this->preview($draft)['hash'], 'reason' => $reason]);
    }

    public function testCalendarListsOccurrencesWithTitleAndBookingCounts(): void
    {
        $from = rawurlencode($this->nextYear('03-01').'T00:00:00Z');
        $until = rawurlencode($this->nextYear('04-30').'T00:00:00Z');

        $this->call('GET', "/api/events/calendar?from=$from&until=$until&search=sunday", $this->reader);

        self::assertSame(200, $this->code(), $this->message());
        self::assertSame(3, $this->json()['total']);
        $first = $this->json()['occurrences'][0];
        self::assertSame($this->ids[0], $first['occurrence']['id']);
        self::assertSame('Sunday service', $first['title']);
        self::assertSame(['confirmed' => 0, 'waitlisted' => 0, 'cancelled' => 0], $first['registrations']);
        self::assertSame('Africa/Johannesburg', $first['occurrence']['timeZone']);

        $this->call('GET', "/api/events/calendar?from=$from&until=$until&eventId={$this->event}&status=CANCELLED", $this->reader);
        self::assertSame(0, $this->json()['total']);
    }

    public function testCalendarRejectsBadRangesStatusesAndPaging(): void
    {
        $from = rawurlencode($this->nextYear('03-01').'T00:00:00Z');
        $late = rawurlencode((new DateTimeImmutable($this->nextYear('03-01')))->modify('+400 days')->format('Y-m-d').'T00:00:00Z');
        foreach ([
            "from=$late&until=$from", "from=$from&until=$late", "from=$from&until=$from", 'from=nope&until=nope', "from=$from",
            "from=$from&until=$from&status=X", "from=$from&until=$late&limit=0", "from=$from&until=$late&eventId=x",
        ] as $query) {
            $this->call('GET', "/api/events/calendar?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
        $until = rawurlencode($this->nextYear('04-30').'T00:00:00Z');
        $this->call('GET', "/api/events/calendar?from=$from&until=$until&status=NOPE", $this->reader);
        self::assertSame(400, $this->code());
    }

    public function testAnOccurrenceShowsItsSeriesPolicyAndHistory(): void
    {
        $this->call('GET', "/api/events/occurrences/{$this->ids[0]}", $this->reader);

        self::assertSame(200, $this->code());
        self::assertSame($this->event, $this->json()['occurrence']['eventId']);
        self::assertSame('Sunday service', $this->json()['title']);
        self::assertSame(3, $this->json()['series']['count']);
        self::assertSame(['occurrenceId' => $this->ids[0], 'open' => false, 'capacity' => null, 'waitlistEnabled' => true, 'nextPosition' => 0, 'version' => 0], $this->json()['registrationPolicy']);

        $this->call('GET', "/api/events/occurrences/{$this->ids[0]}/history", $this->reader);
        self::assertSame('SCHEDULED', $this->json()[0]['action']);
        self::assertSame('Occurrence scheduled.', $this->json()[0]['reason']);
        $this->call('GET', '/api/events/occurrences/'.Uuid::v4()->toRfc4122(), $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/events/occurrences/'.Uuid::v4()->toRfc4122().'/history', $this->reader);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/events/occurrences/zzz', $this->reader);
        self::assertSame(400, $this->code());
    }

    public function testPreviewShowsBeforeAfterAndNeverWrites(): void
    {
        $preview = $this->preview($this->change('RESCHEDULE'));

        self::assertCount(1, $preview['changes']);
        self::assertSame(0, $preview['changes'][0]['before']['version']);
        self::assertSame(1, $preview['changes'][0]['after']['version']);
        self::assertSame($this->nextYear('03-16'), substr($preview['changes'][0]['after']['startsAt'], 0, 10));
        self::assertSame([], $preview['skipped']);
        self::assertSame(0, $this->rows('event_occurrence_history', "action = 'RESCHEDULE'"));
    }

    public function testReschedulingOneDateKeepsItsIdentityAndWritesHistory(): void
    {
        $this->apply('reschedule', $this->change('RESCHEDULE'));

        self::assertSame(204, $this->code());
        $row = $this->connection->fetchAssociative('SELECT * FROM event_occurrences WHERE id = ?', [$this->ids[0]]);
        self::assertIsArray($row);
        self::assertTrue($row['overridden']);
        self::assertSame(1, $row['version']);
        self::assertSame(1, $row['schedule_revision']);
        self::assertSame($this->nextYear(), $row['origin_date']);
        self::assertSame(1, $this->rows('event_occurrence_history', "action = 'RESCHEDULE' AND occurrence_id = '{$this->ids[0]}'"));
        self::assertSame(1, $this->rows('event_audit', "action = 'occurrences.reschedule' AND target_id = '{$this->event}' AND reason = 'Because'"));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT version FROM event_series WHERE event_id = ?', [$this->event]));
    }

    public function testAStaleHashOrVersionConflictsAndRollsBackEverything(): void
    {
        $this->call('POST', '/api/events/occurrences/cancel', $this->manager, ['draft' => $this->change('CANCEL'), 'reviewHash' => str_repeat('0', 64), 'reason' => 'r']);
        self::assertSame(409, $this->code());
        $this->call('POST', '/api/events/occurrences/cancel', $this->manager, ['draft' => $this->change('CANCEL', version: 3), 'reviewHash' => 'x', 'reason' => 'r']);
        self::assertSame(409, $this->code());

        self::assertSame(0, $this->rows('event_occurrences', "status = 'CANCELLED'"));
        self::assertSame(3, $this->rows('event_occurrence_history'));
        self::assertSame(0, $this->rows('event_audit', "action = 'occurrences.cancel'"));
    }

    public function testCancellingCancelsBookingsAndClosesRegistrationAndRestoreDoesNotReopen(): void
    {
        $person = Uuid::v4()->toRfc4122();
        $this->connection->executeStatement("UPDATE event_registration_policies SET open = TRUE, capacity = 5 WHERE occurrence_id = '{$this->ids[0]}'");
        $this->connection->insert('event_registrations', ['id' => Uuid::v4()->toRfc4122(), 'occurrence_id' => $this->ids[0], 'person_id' => $person, 'status' => 'CONFIRMED', 'queue_position' => 1, 'version' => 0, 'created_at' => '2026-01-01 00:00:00+00', 'updated_at' => '2026-01-01 00:00:00+00']);

        $draft = $this->change('CANCEL');
        self::assertSame(1, $this->preview($draft)['confirmedAffected']);
        $this->apply('cancel', $draft);
        self::assertSame(204, $this->code());

        self::assertSame('CANCELLED', $this->connection->fetchOne('SELECT status FROM event_registrations WHERE person_id = ?', [$person]));
        self::assertSame(1, $this->rows('event_registration_history', "action = 'OCCURRENCE_CANCELLED' AND reason = 'Because'"));
        self::assertFalse($this->connection->fetchOne('SELECT open FROM event_registration_policies WHERE occurrence_id = ?', [$this->ids[0]]));
        self::assertSame(5, (int) $this->connection->fetchOne('SELECT capacity FROM event_registration_policies WHERE occurrence_id = ?', [$this->ids[0]]));

        $this->apply('restore', $this->change('RESTORE', version: 1));
        self::assertSame(204, $this->code());
        self::assertSame('SCHEDULED', $this->connection->fetchOne('SELECT status FROM event_occurrences WHERE id = ?', [$this->ids[0]]));
        self::assertSame('CANCELLED', $this->connection->fetchOne('SELECT status FROM event_registrations WHERE person_id = ?', [$person]), 'bookings are not restored');
        self::assertFalse($this->connection->fetchOne('SELECT open FROM event_registration_policies WHERE occurrence_id = ?', [$this->ids[0]]));
    }

    public function testFutureScopeAppliesToTheWholeRemainingSeries(): void
    {
        $draft = $this->change('CANCEL', 'FUTURE', $this->ids[1]);
        self::assertCount(2, $this->preview($draft)['changes']);
        $this->apply('cancel', $draft);

        self::assertSame(204, $this->code());
        self::assertSame(['SCHEDULED', 'CANCELLED', 'CANCELLED'], $this->connection->fetchFirstColumn('SELECT status FROM event_occurrences ORDER BY starts_at'));
    }

    public function testAnExclusiveVenueCannotBeDoubleBooked(): void
    {
        $other = $this->event($this->draft($this->nextYear('05-10'), 1));
        $clash = (string) $this->connection->fetchOne('SELECT id FROM event_occurrences WHERE event_id = ?', [$other]);
        $draft = $this->change('RESCHEDULE', occurrence: $clash, extra: ['date' => $this->nextYear('03-15'), 'startsAt' => '10:30', 'endsAt' => '11:00']);

        $this->call('POST', '/api/events/occurrences/preview', $this->reader, ['draft' => $draft]);

        self::assertSame(409, $this->code());
        self::assertSame($this->message(), 'The exclusive venue is already reserved during a revised date.');
    }

    public function testInvalidChangesAreRejected(): void
    {
        $cases = [
            'unknown action' => [400, $this->change('MOVE')],
            'restore a scheduled date' => [409, $this->change('RESTORE')],
            'missing venue' => [404, ['venueId' => Uuid::v4()->toRfc4122()] + $this->change('RESCHEDULE')],
            'bad time' => [400, ['startsAt' => '25:00'] + $this->change('RESCHEDULE')],
            'unknown occurrence' => [404, $this->change('CANCEL', occurrence: Uuid::v4()->toRfc4122())],
            'no date' => [400, array_diff_key($this->change('RESCHEDULE'), ['date' => 1])],
        ];
        foreach ($cases as $name => [$status, $draft]) {
            $this->call('POST', '/api/events/occurrences/preview', $this->reader, ['draft' => $draft]);
            self::assertSame($status, $this->code(), $name.' '.$this->message());
        }
        $this->call('POST', '/api/events/occurrences/preview', $this->reader, ['draft' => array_diff_key($this->change('CANCEL'), ['expectedVersion' => 1])]);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events/occurrences/cancel', $this->manager, ['draft' => $this->change('CANCEL'), 'reviewHash' => 'x', 'reason' => ' ']);
        self::assertSame(400, $this->code());
        $this->call('POST', '/api/events/occurrences/cancel', $this->manager, ['draft' => $this->change('RESCHEDULE'), 'reviewHash' => 'x', 'reason' => 'r']);
        self::assertSame(400, $this->code(), 'the draft must match the endpoint action');
    }

    public function testPermissionsAreEnforced(): void
    {
        foreach (['reschedule', 'cancel', 'restore', 'preview'] as $path) {
            $this->call('POST', "/api/events/occurrences/$path", null, ['draft' => $this->change('CANCEL')]);
            self::assertSame(401, $this->code(), $path);
        }
        foreach (['reschedule', 'cancel', 'restore'] as $path) {
            $this->call('POST', "/api/events/occurrences/$path", $this->reader, ['draft' => $this->change('CANCEL'), 'reviewHash' => 'x', 'reason' => 'r']);
            self::assertSame(403, $this->code(), $path);
        }
        $this->call('GET', "/api/events/occurrences/{$this->ids[0]}");
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/events/calendar?from=a&until=b');
        self::assertSame(401, $this->code());
        $this->accountWith('nobody', []);
        $this->call('GET', "/api/events/occurrences/{$this->ids[0]}", $this->login('nobody'));
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('event_occurrences', "status = 'CANCELLED' OR overridden"));
    }

    public function testAVolunteerShiftGoesStaleWhenItsOccurrenceIsRescheduled(): void
    {
        $store = new DbalOccurrenceStore($this->connection);
        $activities = new PublishedEventActivities($store, $store);
        $registry = new ActivityRegistry([$activities]);
        $starts = new DateTimeImmutable('2030-01-01T10:00:00Z');
        $shift = static fn (?string $revision): ShiftView => new ShiftView(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122(), 'EVENT_OCCURRENCE', null, $revision, $starts, $starts, 'OPEN', 1);

        $linked = fn (?string $revision): ShiftView => new ShiftView($shift(null)->id, $shift(null)->teamId, $shift(null)->roleId, 'EVENT_OCCURRENCE', $this->ids[0], $revision, $starts, $starts, 'OPEN', 1);
        $revision = $registry->revision($linked(null));
        $registry->require($linked($revision));

        $this->apply('reschedule', $this->change('RESCHEDULE'));
        self::assertSame(204, $this->code());

        self::assertNotSame($revision, $registry->revision($linked(null)));
        $this->expectExceptionMessage('The linked activity was rescheduled.');
        $registry->require($linked($revision));
    }

    public function testActivitiesAreFoundListedAndLockedByKind(): void
    {
        $store = new DbalOccurrenceStore($this->connection);
        $activities = new PublishedEventActivities($store, $store);

        self::assertSame('EVENT_OCCURRENCE', $activities->kind());
        $found = $activities->find($this->ids[1]);
        self::assertNotNull($found);
        self::assertSame('Sunday service', $found->label);
        self::assertTrue($found->active);
        self::assertSame('0', $found->scheduleRevision);
        self::assertSame('Main hall', $found->venueLabel);
        self::assertNull($activities->find(Uuid::v4()->toRfc4122()));

        $from = new DateTimeImmutable($this->nextYear('03-01').'T00:00:00Z');
        $until = $from->modify('+60 days');
        self::assertCount(3, $activities->upcoming($from, $until));
        self::assertCount(2, $activities->upcoming($from, $until, 2));
        self::assertCount(1, $activities->upcoming($from, $until, 5, 2));

        $this->apply('cancel', $this->change('CANCEL'));
        self::assertFalse($activities->find($this->ids[0])?->active);
        self::assertCount(2, $activities->upcoming($from, $until, 10));

        $this->connection->beginTransaction();
        $activities->lock([new ServingActivity('EVENT_OCCURRENCE', $this->ids[2]), new ServingActivity('CHILDREN_SESSION', Uuid::v4()->toRfc4122()), new ServingActivity('EVENT_OCCURRENCE', $this->ids[1]), new ServingActivity('EVENT_OCCURRENCE', $this->ids[2])]);
        $this->connection->rollBack();
        $this->addToAssertionCount(1);
    }
}
