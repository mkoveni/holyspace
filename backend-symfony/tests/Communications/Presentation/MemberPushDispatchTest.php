<?php

declare(strict_types=1);

namespace App\Tests\Communications\Presentation;

use App\Communications\Application\Contract\MemberNotificationKind;
use App\Communications\Application\Contract\MemberNotifications;
use App\Communications\Application\Contract\PushTransportResult;
use App\Communications\Application\PushDeliveryProcessor;
use App\Shared\Application\UnitOfWork;
use App\Tests\Support\IdentityApiTestCase;
use App\Tests\Support\ScriptedPushTransport;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/** Port of the dispatch half of Java `MemberPushApiIntegrationTest`: the outbox, eligibility at send time and recovery. */
final class MemberPushDispatchTest extends IdentityApiTestCase
{
    private const string ROOT = '/api/member/push';
    private const string TOKEN = 'ExponentPushToken[test_device_token_12345]';
    private const string SECRET = 'installation_secret_that_is_long_enough_123456789';
    private const string PROJECT = '11111111-1111-4111-8111-111111111111';

    private string $admin;
    private string $adminId;
    private string $device;
    private ScriptedPushTransport $transport;
    private PushDeliveryProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $transport = self::getContainer()->get(ScriptedPushTransport::class);
        $processor = self::getContainer()->get(PushDeliveryProcessor::class);
        \assert($transport instanceof ScriptedPushTransport && $processor instanceof PushDeliveryProcessor);
        $this->transport = $transport;
        $this->transport->reset();
        $this->processor = $processor;
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->device = Uuid::v4()->toRfc4122();
    }

    /** @return array<string, string> */
    private function registration(string $token = self::TOKEN): array
    {
        return ['installationSecret' => self::SECRET, 'token' => $token, 'platform' => 'android', 'projectId' => self::PROJECT];
    }

    private function enable(): void
    {
        $this->call('PUT', self::ROOT."/devices/{$this->device}", $this->admin, $this->registration());
        self::assertSame(204, $this->code());
        $this->call('PUT', self::ROOT.'/preferences/VOLUNTEERING', $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
    }

    private function notices(): MemberNotifications
    {
        $notices = self::getContainer()->get(MemberNotifications::class);
        \assert($notices instanceof MemberNotifications);

        return $notices;
    }

    private function publish(string $key): string
    {
        $this->notices()->publish($this->adminId, MemberNotificationKind::VolunteerInvitation, $key);

        return (string) $this->connection->fetchOne('SELECT id FROM communication_push_deliveries ORDER BY created_at DESC, id DESC LIMIT 1');
    }

    private function state(string $job): string
    {
        return (string) $this->connection->fetchOne('SELECT status FROM communication_push_deliveries WHERE id = ?', [$job]);
    }

    private function makeDue(string $job): void
    {
        $this->connection->executeStatement("UPDATE communication_push_deliveries SET due_at = now() - interval '1 second' WHERE id = ?", [$job]);
    }

    public function testTheOutboxIsAtomicDeduplicatedAndProviderAcceptanceIsSeparateFromTheReceipt(): void
    {
        $this->enable();
        $job = $this->publish('one');
        $this->publish('one');
        self::assertSame(1, $this->rows('communication_push_deliveries'));
        $tx = self::getContainer()->get(UnitOfWork::class);
        \assert($tx instanceof UnitOfWork);
        try {
            $tx->execute(function (): void {
                $this->notices()->publish($this->adminId, MemberNotificationKind::VolunteerInvitation, 'rollback');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        self::assertSame(1, $this->rows('communication_push_deliveries'));

        $this->processor->dispatch($job);
        self::assertSame('TICKET', $this->state($job));
        self::assertSame([self::TOKEN], $this->transport->tokens, 'the decrypted token reaches only the provider');
        self::assertFalse($this->transport->inTransaction, 'the provider is never called inside a transaction');
        $this->processor->dispatch($job);
        self::assertSame(1, $this->transport->calls, 'the receipt is not due yet');

        $this->makeDue($job);
        $this->processor->dispatch($job);
        self::assertSame('PROVIDER_ACCEPTED', $this->state($job));
        self::assertSame(2, $this->transport->calls);
        self::assertSame('ticket-1', $this->connection->fetchOne('SELECT ticket_id FROM communication_push_deliveries WHERE id = ?', [$job]));
        self::assertFalse($this->transport->inTransaction);
    }

    public function testOptOutAndRemoteRevocationCancelQueuedWorkAndAForeignPushCannotOpen(): void
    {
        $this->enable();
        $job = $this->publish('one');
        $notice = (string) $this->connection->fetchOne('SELECT notification_id FROM communication_push_deliveries');
        $this->account('other');
        $other = $this->login('other');
        $this->call('GET', "/api/member/notifications/$notice", $other);
        self::assertSame(404, $this->code());
        $this->call('GET', "/api/member/notifications/$notice", $this->admin);
        self::assertStringContainsString('VOLUNTEERING', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString(self::TOKEN, (string) $this->client->getResponse()->getContent());

        $this->call('PUT', self::ROOT.'/preferences/VOLUNTEERING', $this->admin, ['enabled' => false, 'expectedVersion' => 1]);
        self::assertSame(204, $this->code());
        $this->processor->dispatch($job);
        self::assertSame(['CANCELLED', 0], [$this->state($job), $this->transport->calls]);

        $this->call('PUT', self::ROOT.'/preferences/VOLUNTEERING', $this->admin, ['enabled' => true, 'expectedVersion' => 2]);
        self::assertSame(204, $this->code());
        $next = $this->publish('two');
        $this->call('DELETE', self::ROOT."/devices/{$this->device}", $this->admin);
        self::assertSame(204, $this->code());
        $this->processor->dispatch($next);
        self::assertSame(['CANCELLED', 0], [$this->state($next), $this->transport->calls]);
    }

    public function testRevokedOrExpiredSessionsAndDisabledAccountsNeverSend(): void
    {
        $this->enable();
        $job = $this->publish('disabled');
        $this->connection->executeStatement("UPDATE user_accounts SET status = 'disabled' WHERE id = ?", [$this->adminId]);
        $this->processor->dispatch($job);
        self::assertSame('CANCELLED', $this->state($job));
        $this->connection->executeStatement("UPDATE user_accounts SET status = 'active' WHERE id = ?", [$this->adminId]);

        $expired = $this->publish('expired');
        $this->connection->executeStatement("UPDATE account_sessions SET expires_at = now() - interval '1 second'");
        $this->processor->dispatch($expired);
        self::assertSame('CANCELLED', $this->state($expired));

        $this->connection->executeStatement("UPDATE account_sessions SET expires_at = now() + interval '1 day'");
        $revoked = $this->publish('revoked');
        $this->connection->executeStatement('DELETE FROM account_sessions');
        $this->processor->dispatch($revoked);
        self::assertSame(['CANCELLED', 0], [$this->state($revoked), $this->transport->calls]);
        self::assertSame('ELIGIBILITY_CHANGED', $this->connection->fetchOne('SELECT error_code FROM communication_push_deliveries WHERE id = ?', [$revoked]));
    }

    public function testTokenRotationCancelsOldJobsAndDeviceNotRegisteredRetiresOnlyTheCurrentRevision(): void
    {
        $this->enable();
        $old = $this->publish('old');
        $this->call('PUT', self::ROOT."/devices/{$this->device}", $this->admin, $this->registration('ExpoPushToken[replacement_token_12345]'));
        self::assertSame(204, $this->code());
        $this->processor->dispatch($old);
        self::assertSame('CANCELLED', $this->state($old));

        $next = $this->publish('new');
        $this->transport->send = new PushTransportResult('FAILED', null, 'DeviceNotRegistered');
        $this->processor->dispatch($next);
        self::assertSame('FAILED', $this->state($next));
        self::assertSame(['ExpoPushToken[replacement_token_12345]'], $this->transport->tokens);
        self::assertSame('INVALID', $this->connection->fetchOne('SELECT status FROM communication_push_devices'));
    }

    public function testAnAmbiguousSendIsNotResubmittedButKnownRateLimitsAreRetried(): void
    {
        $this->enable();
        $unknown = $this->publish('unknown');
        $this->transport->send = new PushTransportResult('UNKNOWN', null, 'TRANSPORT_UNCERTAIN');
        $this->processor->dispatch($unknown);
        $this->processor->dispatch($unknown);
        self::assertSame(['UNKNOWN', 1], [$this->state($unknown), $this->transport->calls]);

        $retry = $this->publish('rate');
        $this->transport->send = new PushTransportResult('RETRY', null, 'RATE_LIMITED');
        $this->processor->dispatch($retry);
        self::assertSame('RETRY', $this->state($retry));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT count(*) FROM communication_push_deliveries WHERE id = ? AND due_at > now() + interval '50 seconds'", [$retry]), 'backoff starts at 60 seconds after the first attempt');
        $this->makeDue($retry);
        $this->processor->dispatch($retry);
        self::assertSame(3, $this->transport->calls);
    }

    public function testRetriesStopAtTheAttemptLimit(): void
    {
        $this->enable();
        $job = $this->publish('exhausted');
        $this->transport->send = new PushTransportResult('RETRY', null, 'RATE_LIMITED');
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->makeDue($job);
            $this->processor->dispatch($job);
        }
        self::assertSame(['FAILED', 5], [$this->state($job), (int) $this->connection->fetchOne('SELECT attempts FROM communication_push_deliveries WHERE id = ?', [$job])]);
    }

    public function testInboxAndPushPreferencesAreIndependent(): void
    {
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame(0, $this->rows('communication_push_deliveries'));
        $this->enable();
        $this->call('PUT', '/api/member/notification-preferences/VOLUNTEERING', $this->admin, ['enabled' => false, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
        $job = $this->publish('push-only');
        $this->call('GET', '/api/member/notifications', $this->admin);
        self::assertSame(0, $this->json()['unreadCount']);
        $this->processor->dispatch($job);
        self::assertSame('TICKET', $this->state($job));

        $this->notices()->publish($this->adminId, MemberNotificationKind::EventBookingUpdated, 'events-off');
        self::assertSame(1, $this->rows('communication_push_deliveries'), 'EVENTS push is still off, so nothing is queued');
    }

    public function testChangingAccountCannotReceiveOrOpenThePreviousAccountsQueuedNotice(): void
    {
        $this->enable();
        $job = $this->publish('old-account');
        $notice = (string) $this->connection->fetchOne('SELECT notification_id FROM communication_push_deliveries');
        $this->account('other');
        $other = $this->login('other');
        $this->connection->executeStatement('DELETE FROM account_sessions WHERE account_id = ?', [$this->adminId]);
        $this->call('PUT', self::ROOT."/devices/{$this->device}", $other, $this->registration());
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/member/notifications/$notice", $other);
        self::assertSame(404, $this->code());
        $this->processor->dispatch($job);
        self::assertSame(['CANCELLED', 0], [$this->state($job), $this->transport->calls]);
    }

    public function testCrashRecoveryNeverResendsAnUncertainSendAndRechecksAReceipt(): void
    {
        $this->enable();
        $sending = $this->publish('crash');
        $this->connection->executeStatement(
            "UPDATE communication_push_deliveries SET status = 'SENDING', claim_id = ?, lease_until = now() - interval '3 minutes' WHERE id = ?",
            [Uuid::v4()->toRfc4122(), $sending],
        );
        $checking = $this->publish('checking');
        $this->connection->executeStatement(
            "UPDATE communication_push_deliveries SET status = 'CHECKING', ticket_id = 'ticket-9', claim_id = ?, lease_until = now() - interval '3 minutes' WHERE id = ?",
            [Uuid::v4()->toRfc4122(), $checking],
        );

        self::assertSame(1, $this->processor->runDue());
        self::assertSame(['UNKNOWN', 'LEASE_EXPIRED'], [$this->state($sending), $this->connection->fetchOne('SELECT error_code FROM communication_push_deliveries WHERE id = ?', [$sending])]);
        self::assertSame('PROVIDER_ACCEPTED', $this->state($checking), 'an interrupted receipt check goes back to TICKET and is checked again');
        self::assertSame(1, $this->transport->calls, 'only the receipt was requested; the uncertain send was not repeated');
    }

    public function testRecoveryRevokesDevicesWhoseLeaseEndedAndOldJobsAreDropped(): void
    {
        $this->enable();
        $stale = $this->publish('stale');
        $this->connection->executeStatement("UPDATE communication_push_deliveries SET created_at = now() - interval '25 hours' WHERE id = ?", [$stale]);
        $this->processor->dispatch($stale);
        self::assertSame(['CANCELLED', 0], [$this->state($stale), $this->transport->calls], 'a push older than a day is dropped, not sent late');

        $this->connection->executeStatement("UPDATE communication_push_devices SET expires_at = now() - interval '1 second'");
        $this->processor->runDue();
        self::assertSame(['REVOKED', 1], [
            $this->connection->fetchOne('SELECT status FROM communication_push_devices'),
            (int) $this->connection->fetchOne('SELECT revision FROM communication_push_devices'),
        ]);
    }
}
