<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\TransportResult;
use App\Communications\Application\DeliveryLifecycle;
use App\Communications\Application\DeliveryProcessor;
use App\Communications\Application\DispatchEligibility;
use App\Communications\Domain\CommunicationException;
use App\Communications\Infrastructure\Persistence\DbalCommunicationAudit;
use App\Communications\Infrastructure\Persistence\DbalDeliverySources;
use App\Communications\Infrastructure\Persistence\DbalDeliveryStore;
use App\Shared\Infrastructure\Persistence\DbalUnitOfWork;
use App\Shared\Infrastructure\SymfonyIdGenerator;
use App\Tests\Communications\Support\RecordingPolicy;
use App\Tests\Communications\Support\ScriptedTransport;
use App\Tests\Support\IdentityApiTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/** The queue against the real schema: a source context's policy, the processor, receipts and row locks. */
final class DeliveryStoreTest extends IdentityApiTestCase
{
    private const string SOURCE = 'children-ministry';
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private DbalDeliveryStore $store;
    private MockClock $clock;
    private RecordingPolicy $policy;
    private ScriptedTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DbalDeliveryStore($this->connection, new SymfonyIdGenerator());
        $this->clock = new MockClock('2026-06-01 10:00:30 UTC');
        $this->policy = new RecordingPolicy(self::SOURCE);
        $this->transport = new ScriptedTransport();
    }

    private function lifecycle(): DeliveryLifecycle
    {
        $sources = new DbalDeliverySources($this->connection);

        return new DeliveryLifecycle($this->store, $this->store, $sources, new DispatchEligibility($sources, [$this->policy]), new DbalUnitOfWork($this->connection), new DbalCommunicationAudit($this->connection, new SymfonyIdGenerator()), $this->clock, $this->transport);
    }

    private function processor(): DeliveryProcessor
    {
        $sources = new DbalDeliverySources($this->connection);

        return new DeliveryProcessor($this->store, $this->store, new DispatchEligibility($sources, [$this->policy]), $this->transport, new DbalUnitOfWork($this->connection), new DbalCommunicationAudit($this->connection, new SymfonyIdGenerator()), $this->clock, new SymfonyIdGenerator());
    }

    private function snapshot(?string $id = null, string $scheduled = '2026-06-01T10:00:00Z', string $recipient = 'guardian@example.org'): DeliverySnapshot
    {
        $id ??= Uuid::v4()->toRfc4122();

        return new DeliverySnapshot($id, Uuid::v4()->toRfc4122(), null, null, $recipient, 'Reminder', 'Hello', str_repeat('a', 64), self::ACTOR, new DateTimeImmutable('2026-06-01T09:00:00Z'), new DateTimeImmutable($scheduled), 'QUEUED', 0, '<'.$id.'@onechurch.delivery>', null, null, null, '', false, 0, 'text/html', 'children.session-reminder', 3);
    }

    private function statusOf(string $id): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM communication_email_deliveries WHERE id = ?', [$id]);
    }

    public function testQueueingPersistsTheFrozenMessageItsSourceAnAuditAndAnEvent(): void
    {
        $snapshot = $this->snapshot();

        $this->lifecycle()->queue(self::SOURCE, $snapshot);

        $row = $this->store->delivery($snapshot->id);
        self::assertNotNull($row);
        self::assertSame(['QUEUED', 'guardian@example.org', 'Reminder', 'Hello', 'text/html', 'children.session-reminder', 3, 0, 0], [$row->status, $row->recipient, $row->subject, $row->body, $row->contentType, $row->templateKey, $row->templateVersion, $row->attempts, $row->version]);
        self::assertSame(str_repeat('a', 64), $row->reviewHash);
        self::assertEquals($snapshot->scheduledAt, $row->scheduledAt);
        self::assertTrue($this->store->belongsTo($snapshot->id, self::SOURCE));
        self::assertFalse($this->store->belongsTo($snapshot->id, 'events'));
        self::assertSame(1, $this->rows('communication_audit', "action = 'communication.email.queued' AND target_id = '{$snapshot->id}' AND actor_id = '".self::ACTOR."'"));
        self::assertSame(['QUEUED'], array_map(static fn ($e): string => $e->type, $this->store->events($snapshot->id, 10, 0)));
        self::assertSame('Reviewed recipient and message approved.', $this->store->events($snapshot->id, 10, 0)[0]->reference);
        self::assertSame([$snapshot->id], array_map(static fn ($s): string => $s->id, array_values($this->store->summaries([$snapshot->communicationId]))));
        self::assertSame($snapshot->id, $this->store->forCommunication($snapshot->communicationId)?->id);
    }

    public function testAnIneligibleOrUnauthorizedQueueingLeavesNothingBehind(): void
    {
        $this->policy->eligible = false;
        $snapshot = $this->snapshot();

        try {
            $this->lifecycle()->queue(self::SOURCE, $snapshot);
            self::fail('Expected a rejection.');
        } catch (CommunicationException) {
        }

        self::assertSame(0, $this->rows('communication_email_deliveries'));
        self::assertSame(0, $this->rows('communication_delivery_sources'));
        self::assertSame(0, $this->rows('communication_audit'));
    }

    public function testADuplicateMessageIdIsRefusedByTheDatabase(): void
    {
        $first = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $first);
        $clash = new DeliverySnapshot(Uuid::v4()->toRfc4122(), Uuid::v4()->toRfc4122(), null, null, 'g@example.org', 's', 'b', str_repeat('a', 64), self::ACTOR, $first->approvedAt, $first->scheduledAt, 'QUEUED', 0, $first->messageId, null, null, null, '', false, 0);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->lifecycle()->queue(self::SOURCE, $clash);
    }

    public function testADueDeliveryIsSentOnceAndRecordedWithItsEvents(): void
    {
        $due = $this->snapshot();
        $later = $this->snapshot(scheduled: '2026-06-01T11:00:00Z');
        $this->lifecycle()->queue(self::SOURCE, $due);
        $this->lifecycle()->queue(self::SOURCE, $later);

        self::assertSame(1, $this->processor()->runDue());
        self::assertSame(0, $this->processor()->runDue(), 'it is not picked up twice');

        self::assertSame(['SENT', 'QUEUED'], [$this->statusOf($due->id), $this->statusOf($later->id)]);
        self::assertSame(['guardian@example.org|Reminder|<'.$due->id.'@onechurch.delivery>'], $this->transport->sent);
        $row = $this->store->delivery($due->id);
        self::assertNotNull($row);
        self::assertSame([1, 2, false], [$row->attempts, $row->version, $row->captured]);
        self::assertNotNull($row->attemptId);
        self::assertNotNull($row->startedAt);
        self::assertNotNull($row->finishedAt);
        $types = array_map(static fn ($e): string => $e->type, $this->store->events($due->id, 10, 0));
        sort($types);
        self::assertSame(['QUEUED', 'SENDING', 'SENT'], $types);
        self::assertSame(1, $this->rows('communication_audit', "action = 'communication.email.sent' AND target_id = '{$due->id}'"));
    }

    public function testSourceEligibilityLostBeforeSendingBlocksThenARetrySendsIt(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $this->policy->eligible = false;

        $this->processor()->runDue();

        self::assertSame(['BLOCKED', []], [$this->statusOf($snapshot->id), $this->transport->sent]);
        self::assertSame('ELIGIBILITY_CHANGED', $this->store->delivery($snapshot->id)?->errorCode);

        $this->policy->eligible = true;
        $this->lifecycle()->retry(self::ACTOR, $snapshot->id, 1, 'Eligibility restored');
        self::assertSame('QUEUED', $this->statusOf($snapshot->id));
        $this->processor()->runDue();
        self::assertSame('SENT', $this->statusOf($snapshot->id));
        self::assertCount(1, $this->transport->sent);
        self::assertSame(['queue', 'retry'], $this->policy->authorized, 'the source was asked at both approvals');
    }

    public function testStaleSendingDeliveriesBecomeUncertainAndAreNeverResent(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $this->connection->executeStatement("UPDATE communication_email_deliveries SET status = 'SENDING', attempts = 1, started_at = ?, attempt_id = ? WHERE id = ?", ['2026-06-01 09:50:00+00', Uuid::v4()->toRfc4122(), $snapshot->id]);

        $this->processor()->runDue();

        self::assertSame(['UNKNOWN', 'PROCESS_INTERRUPTED', []], [$this->statusOf($snapshot->id), $this->store->delivery($snapshot->id)?->errorCode, $this->transport->sent]);
        self::assertSame(1, $this->rows('communication_delivery_events', "delivery_id = '{$snapshot->id}' AND type = 'UNKNOWN' AND reference = 'Sending was interrupted; reconcile with the provider before any new attempt.' AND actor_id IS NULL"));
        $this->processor()->runDue();
        self::assertSame([], $this->transport->sent);
    }

    public function testASendStartedMomentsAgoIsNotTreatedAsStale(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $this->connection->executeStatement("UPDATE communication_email_deliveries SET status = 'SENDING', attempts = 1, started_at = ?, attempt_id = ? WHERE id = ?", ['2026-06-01 10:00:00+00', Uuid::v4()->toRfc4122(), $snapshot->id]);

        $this->processor()->runDue();

        self::assertSame('SENDING', $this->statusOf($snapshot->id));
    }

    public function testDisabledSendingTouchesNothing(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $this->transport->enabled = false;

        self::assertSame(0, $this->processor()->runDue());
        self::assertSame(['QUEUED', 1], [$this->statusOf($snapshot->id), \count($this->store->events($snapshot->id, 10, 0))]);
    }

    public function testProviderReceiptsSettleOnceAndReplaysAreSafe(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $this->processor()->runDue();

        $this->lifecycle()->receipt(self::ACTOR, $snapshot->id, $snapshot->messageId, 'evt-1', 'DELIVERED', 'provider-1');
        $this->lifecycle()->receipt(self::ACTOR, $snapshot->id, $snapshot->messageId, 'evt-1', 'DELIVERED', 'provider-1');

        self::assertSame('DELIVERED', $this->statusOf($snapshot->id));
        self::assertSame(1, $this->rows('communication_delivery_events', "event_key = 'evt-1'"));
        self::assertNotNull($this->store->receipt('evt-1'));
        self::assertSame($snapshot->id, $this->store->receipt('evt-1')->deliveryId);
        self::assertNull($this->store->receipt('evt-2'));
        $this->expectExceptionMessage('Provider event identifier already used.');
        $this->lifecycle()->receipt(self::ACTOR, $snapshot->id, $snapshot->messageId, 'evt-1', 'FAILED', 'provider-1');
    }

    public function testListsPageBySourceStatusAndCountFailuresAndWaiting(): void
    {
        $a = $this->snapshot(scheduled: '2026-06-01T10:00:00Z');
        $b = $this->snapshot(scheduled: '2026-06-01T10:00:10Z');
        $c = $this->snapshot(scheduled: '2026-06-01T12:00:00Z');
        foreach ([$a, $b, $c] as $snapshot) {
            $this->lifecycle()->queue(self::SOURCE, $snapshot);
        }
        $this->transport->result = new TransportResult('FAILED', 'INVALID_EMAIL', false);
        $this->processor()->dispatch($a->id);
        $this->transport->result = new TransportResult('SENT', '', false);
        $this->processor()->dispatch($b->id);
        $other = $this->snapshot();
        $this->connection->insert('communication_email_deliveries', ['id' => $other->id, 'communication_id' => $other->communicationId, 'recipient' => 'x@example.org', 'subject' => 's', 'body' => 'b', 'review_hash' => str_repeat('b', 64), 'approved_by' => self::ACTOR, 'approved_at' => '2026-06-01 09:00:00+00', 'scheduled_at' => '2026-06-01 10:00:00+00', 'status' => 'FAILED', 'message_id' => '<other@x>']);
        $this->connection->insert('communication_delivery_sources', ['delivery_id' => $other->id, 'source_context' => 'events']);

        $mine = $this->store->listForSource(self::SOURCE, null, 10, 0);
        self::assertSame([3, 1, 1], [$mine->total, $mine->failed, $mine->waiting]);
        self::assertSame([$c->id, $b->id, $a->id], array_map(static fn ($s): string => $s->id, $mine->deliveries), 'newest schedule first');
        self::assertSame([$a->id], array_map(static fn ($s): string => $s->id, $this->store->listForSource(self::SOURCE, 'FAILED', 10, 0)->deliveries));
        self::assertSame(3, $this->store->listForSource(self::SOURCE, 'ALL', 10, 0)->total);
        self::assertSame([2, 1], [\count($this->store->listForSource(self::SOURCE, '', 2, 0)->deliveries), \count($this->store->listForSource(self::SOURCE, null, 2, 2)->deliveries)]);
        $everything = $this->store->list(null, 10, 0);
        self::assertSame([4, 2], [$everything->total, $everything->failed]);
        self::assertSame([$b->id], array_map(static fn ($s): string => $s->id, $this->store->list('SENT', 10, 0)->deliveries));
        self::assertSame([$c->id], $this->store->due(new DateTimeImmutable('2026-06-01T13:00:00Z'), 10));
    }

    public function testTwoWorkersCannotClaimTheSameDelivery(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            self::assertNotNull($this->store->lock($snapshot->id));

            $other->executeStatement("SET lock_timeout = '300ms'");
            try {
                new DbalDeliveryStore($other, new SymfonyIdGenerator())->lock($snapshot->id);
                self::fail('A second worker must wait for the first.');
            } catch (DriverException $timeout) {
                self::assertSame('55P03', $timeout->getSQLState());
            }
            $this->connection->rollBack();
            self::assertNotNull(new DbalDeliveryStore($other, new SymfonyIdGenerator())->lock($snapshot->id));
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $other->close();
        }
    }

    public function testStaleRecoverySkipsRowsAnotherWorkerHasLocked(): void
    {
        $snapshot = $this->snapshot();
        $this->lifecycle()->queue(self::SOURCE, $snapshot);
        $this->connection->executeStatement("UPDATE communication_email_deliveries SET status = 'SENDING', attempts = 1, started_at = ?, attempt_id = ? WHERE id = ?", ['2026-06-01 09:00:00+00', Uuid::v4()->toRfc4122(), $snapshot->id]);
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $other->beginTransaction();
            self::assertNotNull(new DbalDeliveryStore($other, new SymfonyIdGenerator())->lock($snapshot->id));

            self::assertSame(0, $this->store->recoverStale(new DateTimeImmutable('2026-06-01T10:00:00Z'), new DateTimeImmutable('2026-06-01T10:00:30Z')));
            $other->rollBack();
            self::assertSame(1, $this->store->recoverStale(new DateTimeImmutable('2026-06-01T10:00:00Z'), new DateTimeImmutable('2026-06-01T10:00:30Z')));
        } finally {
            $other->close();
        }
        self::assertSame('UNKNOWN', $this->statusOf($snapshot->id));
    }
}
