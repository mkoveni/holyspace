<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Payments\Application\Contract\ProbeResult;
use App\Tests\Payments\Support\PaymentApiTestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/** Port of Java PaymentSettingsApiIntegrationTest: the real gateway with scripted provider HTTP and connection probe. */
final class PaymentSettingsApiTest extends PaymentApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->probe()->check = function (): ProbeResult {
            self::assertFalse($this->connection->isTransactionActive());

            return new ProbeResult(true, 'API authentication confirmed. Callback pilot still required.');
        };
        $this->http()->handler = static function (string $method, string $url): string {
            throw new RuntimeException('Unexpected provider endpoint '.$url);
        };
    }

    /**
     * @param array<string, string> $credentials
     *
     * @return array<string, mixed>
     */
    private function settings(string $mode, int $version, bool $enabled, array $credentials): array
    {
        return ['expectedVersion' => $version, 'mode' => $mode, 'enabled' => $enabled, 'monthly' => false, 'publicUrl' => 'https://church.example',
            'backendUrl' => 'https://api.church.example', 'credentials' => $credentials, 'reason' => 'Reviewed provider configuration'];
    }

    /** @return array<string, string> */
    private static function credentials(string $suffix, string $mode = 'test'): array
    {
        return ['yocoKey' => 'sk_'.$mode.'_private-key-'.$suffix, 'webhookSecret' => 'whsec_'.base64_encode('private-webhook-secret-'.$suffix)];
    }

    /** @param array<string, string> $credentials */
    private function save(string $mode, int $version, bool $enabled, array $credentials): void
    {
        $this->call('PUT', '/api/payments/settings/yoco', $this->admin, $this->settings($mode, $version, $enabled, $credentials));
        self::assertSame(204, $this->code(), $this->body());
    }

    /** @param array<string, mixed> $body */
    private function put(array $body, string $provider = 'yoco'): int
    {
        $this->call('PUT', "/api/payments/settings/$provider", $this->admin, $body);

        return $this->code();
    }

    private function check(string $mode, int $version): void
    {
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => $version, 'mode' => $mode]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertTrue($this->json()['successful']);
    }

    private function configured(): void
    {
        $this->save('test', 0, false, self::credentials('old'));
        $this->check('test', 1);
        $this->save('test', 1, true, []);
    }

    /** Ages every connection check by 25 hours, past the evidence immutability trigger (test-only). */
    private function age(): void
    {
        $this->connection->transactional(function (): void {
            $this->connection->executeStatement('SET LOCAL session_replication_role = replica');
            $this->connection->executeStatement("UPDATE payments_configuration_checks SET checked_at = checked_at - interval '25 hours'");
        });
    }

    private function overview(): string
    {
        $this->call('GET', '/api/payments/settings', $this->admin);
        self::assertSame(200, $this->code(), $this->body());

        return $this->body();
    }

    public function testBackendUrlIsIndependentValidatedAuditedAndRequiresANewConnectionCheck(): void
    {
        $this->configured();
        $changed = ['backendUrl' => 'https://new-api.church.example/'] + $this->settings('test', 2, false, []);
        self::assertSame(204, $this->put($changed));
        $settings = $this->overview();
        foreach (['"publicUrl":"https:\/\/church.example"', '"backendUrl":"https:\/\/new-api.church.example"', 'backend URL'] as $expected) {
            self::assertStringContainsString($expected, $settings);
        }
        self::assertSame(2, $this->rows('payments_configuration_revisions'));
        self::assertSame(409, $this->put(['expectedVersion' => 3, 'enabled' => true] + $changed));
        foreach (['', 'http://api.church.example', 'https://api.church.example/api', 'https://localhost', 'https://user:password@api.church.example', 'https://api.church.example?x=1'] as $invalid) {
            self::assertSame(400, $this->put(['expectedVersion' => 3, 'backendUrl' => $invalid] + $changed), $invalid);
        }
        $unchanged = $changed;
        unset($unchanged['backendUrl']);
        self::assertSame(204, $this->put(['expectedVersion' => 3] + $unchanged));
        self::assertSame(2, $this->rows('payments_configuration_revisions'));
    }

    public function testMalformedYocoSigningSecretExplainsTheFieldWithoutCallingTheProviderOrLeakingCredentials(): void
    {
        $key = 'sk_test_private-fixture';
        $secret = 'whsec_invalid-signing-fixture';
        self::assertSame(204, $this->put($this->settings('test', 0, false, ['yocoKey' => $key, 'webhookSecret' => $secret])));
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => 1, 'mode' => 'test']);
        self::assertSame(200, $this->code());
        self::assertFalse($this->json()['successful']);
        self::assertStringContainsString('webhook signing secret is malformed', $this->body());
        self::assertStringNotContainsString($key, $this->body());
        self::assertSame(0, $this->probe()->checks);
        $history = $this->overview();
        self::assertStringContainsString('webhook signing secret is malformed', $history);
        self::assertStringNotContainsString($secret, $history);
        self::assertSame([1, 0], [$this->rows('payments_configuration_checks'), $this->rows('payments_orders')]);
    }

    public function testSettingsAreEncryptedRedactedAndReadsNeverMutate(): void
    {
        $this->save('test', 0, false, self::credentials('old'));
        $cipher = (string) $this->connection->fetchOne('SELECT encrypted_credentials FROM payments_configuration_revisions');
        foreach (['private-key-old', 'private-webhook-secret-old'] as $secret) {
            self::assertStringNotContainsString($secret, $cipher);
        }
        $body = $this->overview();
        self::assertStringContainsString('"yocoKeySet":true', $body);
        self::assertStringContainsString('"webhookSecretSet":true', $body);
        self::assertStringContainsString('"actorName":"Admin"', $body);
        foreach (['private-key-old', 'private-webhook-secret-old', 'encryptedCredentials', $cipher] as $secret) {
            self::assertStringNotContainsString($secret, $body);
        }
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $history = $this->rows('payments_configuration_history');
        $this->overview();
        self::assertSame([$history, 0, 0], [$this->rows('payments_configuration_history'), $this->rows('payments_configuration_checks'), $this->rows('giving_gifts')]);
        $this->call('PUT', '/api/payments/settings/yoco', $this->admin, ['reason' => 'Rotated sk_test_private-key-old'] + $this->settings('test', 1, false, []));
        self::assertSame([400, 'Keep credentials out of the change reason.'], [$this->code(), $this->message()]);
    }

    public function testActivationRequiresASuccessfulCheckForTheSameSavedRevision(): void
    {
        self::assertSame(409, $this->put($this->settings('test', 0, true, self::credentials('old'))));
        self::assertSame(0, $this->rows('payments_configuration_revisions'));
        $this->save('test', 0, false, self::credentials('old'));
        self::assertSame(409, $this->put($this->settings('test', 1, true, [])));
        $this->check('test', 1);
        $this->save('test', 1, true, []);
        $this->call('GET', '/api/payments/providers', $this->admin);
        self::assertStringContainsString('"available":true', $this->body());
        self::assertSame(409, $this->put($this->settings('test', 2, true, self::credentials('new'))));
        $this->save('test', 2, false, self::credentials('new'));
        self::assertSame(2, $this->rows('payments_configuration_revisions'));
        self::assertSame(409, $this->put($this->settings('test', 3, true, [])));
        $this->check('test', 3);
        $this->age();
        self::assertSame(409, $this->put($this->settings('test', 3, true, [])), 'a check older than 24 hours cannot activate');
    }

    public function testBlankCredentialsPreserveEnvironmentValuesAndVersionsPreventLostUpdates(): void
    {
        $this->configured();
        $this->save('live', 2, false, self::credentials('new', 'live'));
        $this->check('live', 3);
        $this->save('live', 3, true, []);
        self::assertSame(2, $this->rows('payments_configuration_revisions'));
        self::assertSame(409, $this->put($this->settings('test', 3, false, [])));
        $this->save('test', 4, true, []);
        self::assertSame(2, $this->rows('payments_configuration_revisions'));
        $this->call('GET', '/api/payments/providers', $this->admin);
        self::assertStringContainsString('"mode":"test"', $this->body());
    }

    public function testSettingsPermissionIsSeparateFromPaymentManagement(): void
    {
        $this->revoke('payments.configure');
        $this->call('GET', '/api/payments/settings', $this->admin);
        self::assertSame(403, $this->code());
        self::assertSame(403, $this->put($this->settings('test', 0, false, self::credentials('old'))));
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => 0, 'mode' => 'test']);
        self::assertSame(403, $this->code());
        $this->call('GET', '/api/payments/settings', null);
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/payments', $this->admin);
        self::assertSame(200, $this->code());
    }

    public function testConfigurationPermissionDoesNotRequireOrGrantFinancialReads(): void
    {
        $this->revoke('payments.manage');
        $this->overview();
        $this->call('GET', '/api/payments', $this->admin);
        self::assertSame(403, $this->code());
    }

    public function testConnectionFailuresAreSafeAuditedAndDoNotActivateOrCharge(): void
    {
        $this->save('test', 0, false, self::credentials('old'));
        $this->probe()->check = static fn () => throw new RuntimeException('Provider echoed private-key-old');
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => 1, 'mode' => 'test']);
        self::assertSame(200, $this->code());
        self::assertFalse($this->json()['successful']);
        self::assertStringNotContainsString('private-key-old', $this->body());
        self::assertStringNotContainsString('private-key-old', $this->overview());
        self::assertSame([1, 0], [$this->rows('payments_configuration_checks'), $this->rows('giving_gifts')]);
        self::assertSame(409, $this->put($this->settings('test', 1, true, [])));
    }

    public function testHistoricalCheckoutsKeepOriginalCredentialsAfterAConfigurationChange(): void
    {
        $this->configured();
        $donor = $this->person('Donor');
        $fund = $this->fund('General');
        $id = $this->created('/api/payments', ['requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $donor, 'fundId' => $fund, 'amountMinor' => 10000, 'provider' => 'yoco']);
        self::assertNotFalse($this->connection->fetchOne('SELECT configuration_id FROM payments_orders WHERE id = ? AND configuration_id IS NOT NULL', [$id]));
        self::assertSame(204, $this->put(['publicUrl' => 'https://new.church.example', 'backendUrl' => 'https://new-api.church.example'] + $this->settings('live', 2, false, self::credentials('new', 'live'))));
        $this->http()->handler = static function (string $method, string $url, array $headers, string $body) use ($id): string {
            self::assertSame(['POST', 'https://payments.yoco.com/api/checkouts'], [$method, $url]);
            self::assertSame('Bearer sk_test_private-key-old', $headers['Authorization']);
            self::assertStringContainsString('https://church.example/payments?order='.$id, $body);

            return '{"id":"co_old","amount":10000,"currency":"ZAR","processingMode":"test","redirectUrl":"https://c.yoco.com/checkout/old"}';
        };
        $this->call('POST', "/api/payments/$id/checkout", $this->admin);
        self::assertSame(200, $this->code(), $this->body());
        self::assertStringContainsString('https:\/\/c.yoco.com\/checkout\/old', $this->body());
        self::assertFalse($this->http()->inTransaction, 'the provider call runs outside transactions');
        $this->call('POST', '/api/payments', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $donor, 'fundId' => $fund, 'amountMinor' => 10000, 'provider' => 'yoco']);
        self::assertSame(503, $this->code(), $this->body());
    }

    public function testAConnectionCheckCannotApproveSettingsChangedDuringTheNetworkCall(): void
    {
        $this->save('test', 0, false, self::credentials('old'));
        $this->probe()->check = function (): ProbeResult {
            $this->connection->executeStatement("UPDATE payments_provider_settings SET version = 2 WHERE provider = 'yoco'");

            return new ProbeResult(true, 'API authentication confirmed.');
        };
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => 1, 'mode' => 'test']);
        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('payments_configuration_checks'));
    }

    public function testPublicUrlsProvidersAndMonthlyOptionsAreValidated(): void
    {
        $bad = $this->settings('test', 0, false, self::credentials('old'));
        self::assertSame(400, $this->put(['publicUrl' => 'http://church.example'] + $bad));
        self::assertSame(400, $this->put(['publicUrl' => 'https://church.example/settings'] + $bad));
        self::assertSame(400, $this->put($bad, 'payfast'));
        self::assertSame(400, $this->put($bad, 'stripe'));
        self::assertSame(400, $this->put(['monthly' => true] + $bad));
    }
}
