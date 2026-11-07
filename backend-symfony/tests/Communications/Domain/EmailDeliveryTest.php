<?php

declare(strict_types=1);

namespace App\Tests\Communications\Domain;

use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\EmailDelivery;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryTest extends TestCase
{
    private function delivery(string $status = 'QUEUED', string $scheduled = '2026-06-01T10:00:00Z'): EmailDelivery
    {
        return new EmailDelivery('d', 'c', null, null, 'guardian@example.org', 'Subject', 'Body', str_repeat('a', 64), 'actor', new DateTimeImmutable('2026-06-01T09:00:00Z'), new DateTimeImmutable($scheduled), $status, 0, '<d@onechurch.delivery>', null, null, null, '', false, 0);
    }

    public function testQueuedAndScheduledDeliveriesAreWaiting(): void
    {
        self::assertTrue($this->delivery('QUEUED')->waiting());
        self::assertTrue($this->delivery('SCHEDULED')->waiting());
        foreach (['SENDING', 'SENT', 'DELIVERED', 'FAILED', 'UNKNOWN', 'CANCELLED', 'BLOCKED'] as $status) {
            self::assertFalse($this->delivery($status)->waiting(), $status);
        }
    }

    public function testClaimingStartsAnAttemptAndBumpsTheVersion(): void
    {
        $now = new DateTimeImmutable('2026-06-01T10:00:01Z');

        $sending = $this->delivery()->claim($now, 'attempt-1');

        self::assertSame(['SENDING', 1, 'attempt-1', 1, '', false, null], [$sending->status, $sending->attempts, $sending->attemptId, $sending->version, $sending->errorCode, $sending->captured, $sending->finishedAt]);
        self::assertEquals($now, $sending->startedAt);
    }

    public function testClaimingClearsTheFinishTimeOfAnEarlierAttempt(): void
    {
        $earlier = $this->delivery('FAILED')->approveRetry('actor', new DateTimeImmutable('2026-06-01T10:30:00Z'));
        self::assertNotNull($earlier->finishedAt);

        $sending = $earlier->claim(new DateTimeImmutable('2026-06-01T10:30:01Z'), 'attempt-2');

        self::assertNull($sending->finishedAt);
    }

    public function testOnlyADueWaitingDeliveryCanBeClaimed(): void
    {
        foreach ([[$this->delivery('SENDING'), '2026-06-01T10:00:01Z'], [$this->delivery('SENT'), '2026-06-01T10:00:01Z'], [$this->delivery('QUEUED', '2026-06-01T11:00:00Z'), '2026-06-01T10:00:00Z']] as [$delivery, $at]) {
            try {
                $delivery->claim(new DateTimeImmutable($at), 'a');
                self::fail('Expected a rejection.');
            } catch (CommunicationException $error) {
                self::assertSame('This delivery is not due.', $error->getMessage());
            }
        }
        self::assertSame('SENDING', $this->delivery('QUEUED', '2026-06-01T10:00:00Z')->claim(new DateTimeImmutable('2026-06-01T10:00:00Z'), 'a')->status, 'due exactly now');
    }

    public function testTransitionRecordsTheOutcomeWithoutTouchingTheAttempt(): void
    {
        $now = new DateTimeImmutable('2026-06-01T10:05:00Z');
        $sending = $this->delivery()->claim(new DateTimeImmutable('2026-06-01T10:00:01Z'), 'attempt-1');

        $sent = $sending->transition('SENT', $sending->scheduledAt, $now, '', true);

        self::assertSame(['SENT', 1, 'attempt-1', true, 2], [$sent->status, $sent->attempts, $sent->attemptId, $sent->captured, $sent->version]);
        self::assertEquals($now, $sent->finishedAt);
    }

    public function testRetryApprovalRequeuesDueNowUnderTheNewApprover(): void
    {
        $now = new DateTimeImmutable('2026-06-01T12:00:00Z');

        $retry = $this->delivery('FAILED')->approveRetry('new-approver', $now);

        self::assertSame(['QUEUED', 'new-approver', '', false, 1], [$retry->status, $retry->approvedBy, $retry->errorCode, $retry->captured, $retry->version]);
        self::assertEquals([$now, $now], [$retry->approvedAt, $retry->scheduledAt]);
    }

    public function testDumpsHideTheRecipientAndMessage(): void
    {
        self::assertStringNotContainsString('guardian@example.org', print_r($this->delivery(), true));
    }
}
