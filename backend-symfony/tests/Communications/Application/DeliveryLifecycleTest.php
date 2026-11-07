<?php

declare(strict_types=1);

namespace App\Tests\Communications\Application;

use App\Communications\Application\DeliveryFingerprint;
use App\Communications\Application\DeliveryLifecycle;
use App\Communications\Domain\CommunicationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DeliveryLifecycleTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private CommunicationWorld $world;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->world = new CommunicationWorld();
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
    }

    private function lifecycle(): DeliveryLifecycle
    {
        return new DeliveryLifecycle($this->world, $this->world, $this->world, $this->world->eligibility(), $this->world, $this->world, $this->clock, $this->world);
    }

    private function refused(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('Expected a rejection.');
        } catch (CommunicationException $error) {
            self::assertSame($message, $error->getMessage());
        }
    }

    public function testQueueingStoresTheDeliveryThenAsksItsSourceThenAudits(): void
    {
        $this->lifecycle()->queue(CommunicationWorld::SOURCE, $this->world->snapshot());

        self::assertSame(['insert', 'associate', 'authorize:queue', 'eligible', 'audit:communication.email.queued', 'event:QUEUED'], $this->world->log);
        self::assertSame('QUEUED', $this->world->rows['d1']->status);
        self::assertSame(CommunicationWorld::SOURCE, $this->world->contexts['d1']);
        self::assertSame('Reviewed recipient and message approved.', $this->world->events[0][5]);
    }

    public function testOnlyAFreshApprovedDeliveryCanBeQueued(): void
    {
        foreach ([$this->world->snapshot(status: 'SENT'), $this->world->snapshot(attempts: 1), $this->world->snapshot(version: 2)] as $stale) {
            $this->refused('A new approved delivery is required.', fn () => $this->lifecycle()->queue(CommunicationWorld::SOURCE, $stale));
        }
        self::assertSame([], $this->world->log);
    }

    public function testASourceThatDeniesOrIsNoLongerEligibleStopsTheQueueingTransaction(): void
    {
        $this->world->deniedOperation = 'queue';
        $this->refused('You are not allowed to perform this operation.', fn () => $this->lifecycle()->queue(CommunicationWorld::SOURCE, $this->world->snapshot()));

        $this->world->deniedOperation = null;
        $this->world->eligible = false;
        $this->refused('Sender, recipient or source eligibility changed. Review before retrying.', fn () => $this->lifecycle()->queue(CommunicationWorld::SOURCE, $this->world->snapshot('d2')));

        self::assertNotContains('audit:communication.email.queued', $this->world->log);
        self::assertSame(0, $this->world->depth);
    }

    public function testADeliveryFromAnUnregisteredSourceIsForbidden(): void
    {
        $this->world->sourceName = 'someone-else';

        $this->refused('You are not allowed to perform this operation.', fn () => $this->lifecycle()->queue(CommunicationWorld::SOURCE, $this->world->snapshot()));
    }

    /** @return iterable<string, array{string}> */
    public static function cancellable(): iterable
    {
        foreach (['QUEUED', 'SCHEDULED', 'FAILED', 'BLOCKED'] as $status) {
            yield $status => [$status];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cancellable')]
    public function testWaitingAndDefinitelyFailedDeliveriesCanBeCancelled(string $status): void
    {
        $this->world->seed($status);

        $this->lifecycle()->cancel(self::ACTOR, 'd1', 0, '  Parent asked us not to  ');

        self::assertSame(['lock', 'authorize:cancel', 'save:CANCELLED', 'event:CANCELLED', 'audit:communication.email.cancelled'], $this->world->log);
        self::assertSame(['CANCELLED', 1], [$this->world->rows['d1']->status, $this->world->rows['d1']->version]);
        self::assertSame('Parent asked us not to', $this->world->events[0][5]);
    }

    public function testInProgressAcceptedAndUncertainSendsCannotBeCancelled(): void
    {
        foreach (['SENDING', 'SENT', 'DELIVERED', 'UNKNOWN', 'CANCELLED'] as $status) {
            $this->world->seed($status);
            $this->refused('An in-progress, accepted or uncertain send cannot be cancelled.', fn () => $this->lifecycle()->cancel(self::ACTOR, 'd1', 0, 'Reason'));
        }
        self::assertNotContains('save:CANCELLED', $this->world->log);
    }

    public function testCancelAndRetryNeedAReasonTheRightVersionAndAnExistingDelivery(): void
    {
        $this->world->seed('FAILED');
        $this->refused('Enter text of at most 500 characters.', fn () => $this->lifecycle()->cancel(self::ACTOR, 'd1', 0, ' '));
        $this->refused('Enter text of at most 500 characters.', fn () => $this->lifecycle()->retry(self::ACTOR, 'd1', 0, str_repeat('x', 501)));
        $this->refused('This record changed. Refresh before saving.', fn () => $this->lifecycle()->cancel(self::ACTOR, 'd1', 3, 'Reason'));
        $this->refused('Delivery was not found.', fn () => $this->lifecycle()->cancel(self::ACTOR, 'nope', 0, 'Reason'));
        $this->world->deniedOperation = 'cancel';
        $this->refused('You are not allowed to perform this operation.', fn () => $this->lifecycle()->cancel(self::ACTOR, 'd1', 0, 'Reason'));
        self::assertSame('FAILED', $this->world->rows['d1']->status);
    }

    public function testRetryRequeuesAFailedDeliveryUnderTheNewApprover(): void
    {
        $this->world->seed('FAILED', attempts: 1);

        $this->lifecycle()->retry(self::ACTOR, 'd1', 0, 'Fixed the address');

        self::assertSame(['lock', 'authorize:retry', 'eligible', 'save:QUEUED', 'event:RETRY_APPROVED', 'audit:communication.email.retry'], $this->world->log);
        $row = $this->world->rows['d1'];
        self::assertSame(['QUEUED', self::ACTOR, 1], [$row->status, $row->approvedBy, $row->attempts]);
        self::assertEquals($this->clock->now(), $row->scheduledAt);
    }

    public function testOnlyDefiniteFailuresAreRetriedAndAtMostThreeTimes(): void
    {
        foreach (['QUEUED', 'SENDING', 'SENT', 'UNKNOWN', 'DELIVERED', 'CANCELLED'] as $status) {
            $this->world->seed($status);
            $this->refused('Only definite failures or blocked deliveries may be retried. Reconcile uncertain outcomes with the provider.', fn () => $this->lifecycle()->retry(self::ACTOR, 'd1', 0, 'Reason'));
        }
        $this->world->seed('FAILED', attempts: 3);
        $this->refused('The delivery attempt limit is reached.', fn () => $this->lifecycle()->retry(self::ACTOR, 'd1', 0, 'Reason'));
        $this->world->seed('BLOCKED', attempts: 2);
        $this->lifecycle()->retry(self::ACTOR, 'd1', 0, 'Reason');
        self::assertSame('QUEUED', $this->world->rows['d1']->status);
    }

    public function testRetryRechecksEligibilityBeforeRequeueing(): void
    {
        $this->world->seed('BLOCKED', attempts: 1);
        $this->world->eligible = false;

        $this->refused('Sender, recipient or source eligibility changed. Review before retrying.', fn () => $this->lifecycle()->retry(self::ACTOR, 'd1', 0, 'Reason'));

        self::assertSame('BLOCKED', $this->world->rows['d1']->status);
    }

    private function receipt(string $key = 'evt-1', string $status = 'DELIVERED', string $message = '<d1@onechurch.delivery>', string $reference = 'provider-ref'): void
    {
        $this->lifecycle()->receipt(self::ACTOR, 'd1', $message, $key, $status, $reference);
    }

    public function testAProviderReceiptSettlesALiveAcceptedDelivery(): void
    {
        $this->world->seed('SENT');

        $this->receipt();

        self::assertSame(['lock', 'authorize:receipt', 'save:DELIVERED', 'event:DELIVERED', 'audit:communication.email.receipt'], $this->world->log);
        self::assertSame(['DELIVERED', ''], [$this->world->rows['d1']->status, $this->world->rows['d1']->errorCode]);
        self::assertSame(['evt-1', DeliveryFingerprint::of('d1', '<d1@onechurch.delivery>', 'DELIVERED', 'provider-ref')], [$this->world->events[0][3], $this->world->events[0][4]]);
    }

    public function testAFailedReceiptSettlesAnUncertainDeliveryWithAProviderFailureCode(): void
    {
        $this->world->seed('UNKNOWN');

        $this->receipt(status: 'FAILED');

        self::assertSame(['FAILED', 'PROVIDER_FAILURE'], [$this->world->rows['d1']->status, $this->world->rows['d1']->errorCode]);
    }

    public function testReplayingTheSameReceiptChangesNothingButAReusedKeyWithOtherContentIsRefused(): void
    {
        $this->world->seed('SENT');
        $this->receipt();
        $this->world->log = [];

        $this->receipt();
        self::assertSame(['lock', 'authorize:receipt'], $this->world->log, 'a replay is accepted and writes nothing');

        $this->refused('Provider event identifier already used.', fn () => $this->receipt(reference: 'another-ref'));
        $this->refused('Provider event identifier already used.', fn () => $this->receipt(status: 'FAILED'));
        self::assertSame('DELIVERED', $this->world->rows['d1']->status);
    }

    public function testReceiptsMustMatchALiveProviderAcceptedMessage(): void
    {
        $message = 'Receipt does not match a live SMTP-accepted delivery.';
        foreach (['QUEUED', 'SENDING', 'FAILED', 'DELIVERED', 'CANCELLED'] as $status) {
            $this->world->seed($status);
            $this->refused($message, fn () => $this->receipt());
        }
        $this->world->seed('SENT');
        $this->refused($message, fn () => $this->receipt(message: '<other@onechurch.delivery>'));
        $this->world->capture = true;
        $this->refused($message, fn () => $this->receipt());
        $this->world->capture = false;
        $this->world->rows['d1'] = $this->world->rows['d1']->transition('SENT', $this->world->rows['d1']->scheduledAt, $this->clock->now(), '', true);
        $this->refused($message, fn () => $this->receipt());
    }

    public function testReceiptInputIsValidated(): void
    {
        $this->world->seed('SENT');
        $this->refused('Choose a delivered or failed provider outcome.', fn () => $this->receipt(status: 'SENT'));
        $this->refused('Enter text of at most 200 characters.', fn () => $this->receipt(key: str_repeat('k', 201)));
        $this->refused('Enter text of at most 500 characters.', fn () => $this->receipt(reference: ' '));
        $this->refused('Delivery was not found.', fn () => $this->lifecycle()->receipt(self::ACTOR, 'nope', 'm', 'k', 'DELIVERED', 'r'));
    }

    public function testFingerprintsKeepFieldBoundaries(): void
    {
        self::assertNotSame(DeliveryFingerprint::of('ab', 'c'), DeliveryFingerprint::of('a', 'bc'));
        self::assertSame(DeliveryFingerprint::of('a', 'b'), DeliveryFingerprint::of('a', 'b'));
        self::assertSame(hash('sha256', '1:a1:b'), DeliveryFingerprint::of('a', 'b'));
        self::assertSame(hash('sha256', '1:é1:b'), DeliveryFingerprint::of('é', 'b'), 'a two-byte character is one UTF-16 unit');
        self::assertSame(hash('sha256', '2:😀'), DeliveryFingerprint::of('😀'), 'an astral character is two UTF-16 units');
    }
}
