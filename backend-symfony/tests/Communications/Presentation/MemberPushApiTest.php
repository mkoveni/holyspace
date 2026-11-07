<?php

declare(strict_types=1);

namespace App\Tests\Communications\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Port of the registration, preference and revocation parts of Java `MemberPushApiIntegrationTest`. The dispatch
 * assertions (tickets, receipts, retries, cancellation of queued jobs) arrive with the push queue in A3.
 */
final class MemberPushApiTest extends IdentityApiTestCase
{
    private const string ROOT = '/api/member/push';
    private const string TOKEN = 'ExponentPushToken[test_device_token_12345]';
    private const string SECRET = 'installation_secret_that_is_long_enough_123456789';
    private const string PROJECT = '11111111-1111-4111-8111-111111111111';

    private string $admin;
    private string $adminId;
    private string $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminId = $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->device = Uuid::v4()->toRfc4122();
    }

    /** @return array<string, string> */
    private function registration(string $secret = self::SECRET, string $token = self::TOKEN): array
    {
        return ['installationSecret' => $secret, 'token' => $token, 'platform' => 'android', 'projectId' => self::PROJECT];
    }

    /** @param array<string, mixed>|null $body */
    private function register(string $caller, ?array $body = null): int
    {
        $this->call('PUT', self::ROOT."/devices/{$this->device}", $caller, $body ?? $this->registration());

        return $this->code();
    }

    private function enable(): void
    {
        self::assertSame(204, $this->register($this->admin), $this->code() >= 400 ? $this->message() : '');
        $this->call('PUT', self::ROOT.'/preferences/VOLUNTEERING', $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());
    }

    private function body(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    /** @return array<string, mixed> */
    private function deviceRow(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM communication_push_devices WHERE id = ?', [$this->device]);
        self::assertIsArray($row);

        return $row;
    }

    public function testRegistrationIsPrivateSessionBoundAndInstallationSecretProtected(): void
    {
        $this->call('GET', self::ROOT);
        self::assertSame(401, $this->code());
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame([true, self::PROJECT, false], [$this->json()['available'], $this->json()['projectId'], $this->json()['preferences'][0]['enabled']]);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->enable();
        $this->call('GET', self::ROOT, $this->admin);
        $device = $this->json()['devices'][0];
        self::assertSame([$this->device, 'android', 'ACTIVE', true], [$device['id'], $device['platform'], $device['status'], $device['currentSession']]);
        self::assertMatchesRegularExpression('/Z\z/', (string) $device['expiresAt']);
        foreach ([self::TOKEN, self::SECRET, 'secretHash', 'tokenHash', 'sessionId', 'encryptedToken'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $row = $this->deviceRow();
        self::assertStringNotContainsString(self::TOKEN, (string) $row['encrypted_token']);
        self::assertSame(hash('sha256', self::SECRET), $row['secret_hash']);

        $revision = (int) $row['revision'];
        self::assertSame(204, $this->register($this->admin), 'a plain renewal is idempotent');
        self::assertSame($revision, (int) $this->deviceRow()['revision'], 'and keeps the revision, so queued jobs stay valid');

        self::assertSame(403, $this->register($this->admin, $this->registration('wrong_installation_secret_but_long_enough_123456789')));

        $this->account('other');
        $other = $this->login('other');
        $this->call('GET', self::ROOT, $other);
        self::assertStringNotContainsString($this->device, $this->body());
        $this->call('DELETE', self::ROOT."/devices/{$this->device}", $other);
        self::assertSame(404, $this->code());
        self::assertSame(409, $this->register($other), 'the previous account is still signed in');
        self::assertSame('Sign out of the previous account before enabling push on this device.', $this->message());
    }

    public function testRegistrationRulesAndSetup(): void
    {
        foreach ([
            ['installationSecret' => 'short'] + $this->registration(),
            ['token' => 'not-a-push-token'] + $this->registration(),
            ['token' => 'ExpoPushToken[short]'] + $this->registration(),
            ['platform' => 'web'] + $this->registration(),
        ] as $body) {
            self::assertSame(400, $this->register($this->admin, $body));
            self::assertSame('Choose a valid device push registration.', $this->message());
        }
        foreach ([[], ['projectId' => 'nope'] + $this->registration(), ['token' => str_repeat('t', 241)] + $this->registration()] as $body) {
            self::assertSame(400, $this->register($this->admin, $body));
        }
        self::assertSame(409, $this->register($this->admin, ['projectId' => Uuid::v4()->toRfc4122()] + $this->registration()));
        self::assertSame('Your church has not completed device push setup.', $this->message());
        $this->call('PUT', self::ROOT.'/devices/not-a-uuid', $this->admin, $this->registration());
        self::assertSame(400, $this->code());
        self::assertSame(0, $this->rows('communication_push_devices'));

        self::assertSame(204, $this->register($this->admin, ['projectId' => strtoupper(self::PROJECT), 'token' => 'ExpoPushToken[upper_case_project_ok]'] + $this->registration()));
        self::assertSame(self::PROJECT, $this->deviceRow()['project_id']);
    }

    public function testTheLeaseEndsWithTheSessionAndAnExpiredSessionCannotRegister(): void
    {
        $this->connection->executeStatement("UPDATE account_sessions SET expires_at = now() + interval '2 days' WHERE account_id = ?", [$this->adminId]);
        $this->enable();
        $session = $this->connection->fetchOne('SELECT expires_at FROM account_sessions WHERE account_id = ?', [$this->adminId]);
        self::assertSame($session, $this->deviceRow()['expires_at']);

        $this->connection->executeStatement("UPDATE account_sessions SET expires_at = now() + interval '90 days' WHERE account_id = ?", [$this->adminId]);
        self::assertSame(204, $this->register($this->admin));
        self::assertTrue((bool) $this->connection->fetchOne("SELECT expires_at BETWEEN now() + interval '29 days' AND now() + interval '31 days' FROM communication_push_devices WHERE id = ?", [$this->device]), 'capped at 30 days');
    }

    public function testRevocationIsOwnAccountAndIdempotentAndRenewalReactivates(): void
    {
        $this->enable();
        $this->call('DELETE', self::ROOT."/devices/{$this->device}", $this->admin);
        self::assertSame(204, $this->code());
        $row = $this->deviceRow();
        self::assertSame(['REVOKED', 1], [$row['status'], (int) $row['revision']]);
        $this->call('DELETE', self::ROOT."/devices/{$this->device}", $this->admin);
        self::assertSame(204, $this->code());
        self::assertSame(1, (int) $this->deviceRow()['revision'], 'revoking again changes nothing');
        $this->call('DELETE', self::ROOT.'/devices/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame(404, $this->code());
        self::assertSame('Push device was not found.', $this->message());

        self::assertSame(204, $this->register($this->admin));
        self::assertSame(['ACTIVE', 2], [$this->deviceRow()['status'], (int) $this->deviceRow()['revision']]);
    }

    public function testTokenRotationAndReinstallMoveTheRevisionAndRetireTheOldInstallation(): void
    {
        $this->enable();
        self::assertSame(204, $this->register($this->admin, $this->registration(token: 'ExpoPushToken[replacement_token_12345]')));
        self::assertSame(1, (int) $this->deviceRow()['revision'], 'a rotated token invalidates jobs for the old one');

        $first = $this->device;
        $this->device = Uuid::v4()->toRfc4122();
        self::assertSame(204, $this->register($this->admin, $this->registration('a_new_installation_secret_that_is_long_enough_42', 'ExpoPushToken[replacement_token_12345]')));
        self::assertSame(['REVOKED', 2], [
            $this->connection->fetchOne('SELECT status FROM communication_push_devices WHERE id = ?', [$first]),
            (int) $this->connection->fetchOne('SELECT revision FROM communication_push_devices WHERE id = ?', [$first]),
        ], 'reinstalling on the same account retires its old installation of the token');
        self::assertSame(1, $this->rows('communication_push_devices', "status = 'ACTIVE'"));
    }

    public function testATokenActiveOnAnotherAccountIsRefused(): void
    {
        $this->enable();
        $this->account('other');
        $other = $this->login('other');
        $this->device = Uuid::v4()->toRfc4122();
        self::assertSame(409, $this->register($other, $this->registration('another_installation_secret_long_enough_1234567')));
        self::assertSame('This push token is already registered. Sign out of the previous device registration first.', $this->message());
    }

    public function testChangingAccountNeedsThePreviousSessionToHaveEnded(): void
    {
        $this->enable();
        $otherId = $this->account('other');
        $other = $this->login('other');
        $this->connection->executeStatement('DELETE FROM account_sessions WHERE account_id = ?', [$this->adminId]);

        self::assertSame(204, $this->register($other));
        $row = $this->deviceRow();
        self::assertSame([$otherId, 'ACTIVE', 1], [$row['account_id'], $row['status'], (int) $row['revision']]);
        $this->call('GET', self::ROOT, $other);
        self::assertTrue($this->json()['devices'][0]['currentSession']);
    }

    public function testPreferencesAreVersionedDefaultOffAndIndependentOfTheInbox(): void
    {
        $this->call('GET', self::ROOT, $this->admin);
        self::assertSame([['topic' => 'VOLUNTEERING', 'enabled' => false, 'version' => 0], ['topic' => 'EVENTS', 'enabled' => false, 'version' => 0]], $this->json()['preferences']);
        self::assertSame([], $this->json()['devices']);
        self::assertSame(0, $this->rows('communication_push_devices'));
        self::assertSame(0, $this->rows('communication_push_preferences'));

        $this->enable();
        $this->call('PUT', self::ROOT.'/preferences/VOLUNTEERING', $this->admin, ['enabled' => false, 'expectedVersion' => 1]);
        self::assertSame(204, $this->code());
        $this->call('PUT', self::ROOT.'/preferences/VOLUNTEERING', $this->admin, ['enabled' => true, 'expectedVersion' => 1]);
        self::assertSame(409, $this->code());
        self::assertSame('Push preferences changed. Refresh and try again.', $this->message());
        foreach ([['UNKNOWN', ['enabled' => true, 'expectedVersion' => 0]], ['EVENTS', ['enabled' => true, 'expectedVersion' => -1]]] as [$topic, $body]) {
            $this->call('PUT', self::ROOT."/preferences/$topic", $this->admin, $body);
            self::assertSame(400, $this->code());
        }
        $this->call('PUT', self::ROOT.'/preferences/UNKNOWN', $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame('Choose a valid push topic and version.', $this->message());
        $this->call('PUT', self::ROOT.'/preferences/EVENTS', $this->admin, ['expectedVersion' => 0]);
        self::assertSame(400, $this->code());

        $this->call('GET', '/api/member/notification-preferences', $this->admin);
        self::assertSame([true, 0], [$this->json()[0]['enabled'], $this->json()[0]['version']], 'the inbox preference is separate');
    }
}
