<?php

declare(strict_types=1);

namespace App\Tests\Communications\Application;

use App\Communications\Application\Contract\TransportResult;
use App\Communications\Application\DeliveryProcessor;
use App\Tests\People\Application\SequentialIds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/** Claim in one transaction, send in none, record in another: a slow mail server never holds a database lock. */
final class DeliveryProcessorTest extends TestCase
{
    private CommunicationWorld $world;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->world = new CommunicationWorld();
        $this->clock = new MockClock('2026-06-01 10:00:30 UTC');
    }

    private function processor(): DeliveryProcessor
    {
        return new DeliveryProcessor($this->world, $this->world, $this->world->eligibility(), $this->world, $this->world, $this->world, $this->clock, new SequentialIds());
    }

    public function testADueDeliveryIsClaimedSentOutsideAnyTransactionAndRecorded(): void
    {
        $this->world->seed();

        $this->processor()->dispatch('d1');

        self::assertSame(['lock', 'eligible', 'save:SENDING', 'event:SENDING', 'send:SENDING', 'lock', 'save:SENT', 'event:SENT', 'audit:communication.email.sent'], $this->world->log);
        self::assertSame([0], $this->world->sendDepths, 'the transport ran with no transaction open');
        $row = $this->world->rows['d1'];
        self::assertSame(['SENT', 1, 2, ''], [$row->status, $row->attempts, $row->version, $row->errorCode]);
        self::assertNotNull($row->attemptId);
    }

    public function testACapturedSendIsMarkedAndNotedAsLocal(): void
    {
        $this->world->seed();
        $this->world->result = new TransportResult('SENT', '', true);

        $this->processor()->dispatch('d1');

        self::assertTrue($this->world->rows['d1']->captured);
        self::assertSame('Captured by the local test inbox.', $this->world->events[1][5]);
    }

    public function testDefiniteFailuresAndUncertainOutcomesAreRecordedAsReported(): void
    {
        foreach ([['FAILED', 'INVALID_EMAIL'], ['UNKNOWN', 'SMTP_UNCERTAIN']] as [$status, $code]) {
            $this->world->seed();
            $this->world->result = new TransportResult($status, $code, false);

            $this->processor()->dispatch('d1');

            self::assertSame([$status, $code], [$this->world->rows['d1']->status, $this->world->rows['d1']->errorCode]);
            self::assertContains('audit:communication.email.'.strtolower($status), $this->world->log);
        }
    }

    public function testATransportThatThrowsLeavesTheOutcomeUncertainNeverFailed(): void
    {
        $this->world->seed();
        $this->world->sendFailure = new RuntimeException('connection reset after DATA');

        $this->processor()->dispatch('d1');

        self::assertSame(['UNKNOWN', 'TRANSPORT_UNCERTAIN', 1], [$this->world->rows['d1']->status, $this->world->rows['d1']->errorCode, $this->world->rows['d1']->attempts]);
    }

    public function testADeliveryWhoseSourceNoLongerAllowsItIsBlockedWithoutSending(): void
    {
        $this->world->seed();
        $this->world->eligible = false;

        $this->processor()->dispatch('d1');

        self::assertSame(['lock', 'eligible', 'save:BLOCKED', 'event:BLOCKED'], $this->world->log);
        self::assertSame(['BLOCKED', 'ELIGIBILITY_CHANGED'], [$this->world->rows['d1']->status, $this->world->rows['d1']->errorCode]);
        self::assertSame(0, $this->world->sends);
    }

    public function testDeliveriesThatAreNotDueOrNotWaitingAreLeftAlone(): void
    {
        $this->world->seed('QUEUED', 'future', '2026-06-01T11:00:00Z');
        $this->world->seed('SENT', 'done');
        $this->world->seed('CANCELLED', 'cancelled');

        foreach (['future', 'done', 'cancelled'] as $id) {
            $this->processor()->dispatch($id);
        }

        self::assertSame(0, $this->world->sends);
        self::assertSame(['QUEUED', 'SENT', 'CANCELLED'], array_map(static fn ($d): string => $d->status, array_values($this->world->rows)));
    }

    public function testAnOutcomeIsDroppedWhenAnotherAttemptNowOwnsTheDelivery(): void
    {
        $this->world->seed();
        $world = $this->world;
        $world->result = new TransportResult('SENT', '', false);
        $processor = $this->processor();
        $world->hijack = static function (CommunicationWorld $w): void {
            $w->rows['d1'] = $w->rows['d1']->transition('UNKNOWN', $w->rows['d1']->scheduledAt, new DateTimeImmutable(), 'PROCESS_INTERRUPTED', false);
        };
        $processor->dispatch('d1');

        self::assertSame('UNKNOWN', $world->rows['d1']->status, 'the late outcome did not overwrite recovery');
        self::assertNotContains('audit:communication.email.sent', $world->log);
    }

    public function testRunDueDoesNothingWhileSendingIsDisabled(): void
    {
        $this->world->seed();
        $this->world->enabled = false;

        self::assertSame(0, $this->processor()->runDue());

        self::assertSame([], $this->world->log);
        self::assertSame('QUEUED', $this->world->rows['d1']->status);
    }

    public function testRunDueRecoversStaleSendsThenSendsDueDeliveriesOldestFirst(): void
    {
        $this->world->seed('QUEUED', 'late', '2026-06-01T10:00:20Z');
        $this->world->seed('QUEUED', 'early', '2026-06-01T10:00:00Z');
        $this->world->seed('QUEUED', 'future', '2026-06-01T10:05:00Z');
        $stuck = $this->world->seed('QUEUED', 'stuck', '2026-06-01T08:00:00Z');
        $this->world->rows['stuck'] = $stuck->claim(new DateTimeImmutable('2026-06-01T09:00:00Z'), 'att');
        $this->world->log = [];

        $attempted = $this->processor()->runDue();

        self::assertSame(2, $attempted);
        self::assertSame('recover', $this->world->entries()[0]);
        self::assertSame('UNKNOWN', $this->world->rows['stuck']->status);
        self::assertSame(['SENT', 'SENT', 'QUEUED'], [$this->world->rows['early']->status, $this->world->rows['late']->status, $this->world->rows['future']->status]);
        self::assertSame(['send:SENDING', 'send:SENDING'], array_values(array_filter($this->world->entries(), static fn (string $e): bool => str_starts_with($e, 'send:'))));
    }

    public function testAtMostTwentyFiveDeliveriesAreTakenPerRun(): void
    {
        foreach (range(1, 30) as $i) {
            $this->world->seed('QUEUED', 'd'.str_pad((string) $i, 2, '0', \STR_PAD_LEFT), sprintf('2026-06-01T10:00:%02dZ', $i % 30));
        }

        self::assertSame(25, $this->processor()->runDue());
        self::assertSame(25, $this->world->sends);
    }
}
