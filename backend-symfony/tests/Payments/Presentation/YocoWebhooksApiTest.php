<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Tests\Payments\Support\PaymentApiTestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/** Port of Java YocoWebhooksApiIntegrationTest: webhook administration against a scripted Yoco API. */
final class YocoWebhooksApiTest extends PaymentApiTestCase
{
    private const string SECRET = 'whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    /** @var list<array<string, string>> */
    private array $subscriptions = [];
    private int $registrations = 0;
    private bool $loseResponse = false;
    private bool $changeVersion = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http()->handler = function (string $method, string $url, array $headers, string $body): string {
            self::assertFalse($this->connection->isTransactionActive(), 'provider calls run outside transactions');
            if ('GET' === $method) {
                return json_encode(['subscriptions' => $this->subscriptions], \JSON_THROW_ON_ERROR);
            }
            if ('DELETE' === $method) {
                $this->subscriptions = array_values(array_filter($this->subscriptions, static fn (array $v): bool => !str_ends_with($url, '/'.$v['id'])));

                return '{}';
            }
            ++$this->registrations;
            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $entry = ['id' => 'sub_fixture', 'name' => (string) $payload['name'], 'url' => (string) $payload['url'], 'mode' => 'test'];
            $this->subscriptions[] = $entry;
            if ($this->changeVersion) {
                $this->connection->executeStatement("UPDATE payments_provider_settings SET version = 2 WHERE provider = 'yoco'");
            }
            if ($this->loseResponse) {
                throw new RuntimeException('provider response containing a secret must not escape');
            }

            return json_encode($entry + ['secret' => self::SECRET], \JSON_THROW_ON_ERROR);
        };
    }

    /** @return array<string, mixed> */
    private static function settings(int $version, bool $enabled): array
    {
        return ['expectedVersion' => $version, 'mode' => 'test', 'enabled' => $enabled, 'monthly' => false, 'publicUrl' => 'https://church.example',
            'backendUrl' => 'https://api.church.example', 'credentials' => ['yocoKey' => 'sk_test_fixture'], 'reason' => 'Reviewed test setup'];
    }

    private function saved(): void
    {
        $this->call('PUT', '/api/payments/settings/yoco', $this->admin, self::settings(0, false));
        self::assertSame(204, $this->code(), $this->body());
    }

    /** @return array<string, mixed> */
    private static function change(string $id, int $version): array
    {
        return ['requestId' => $id, 'mode' => 'test', 'expectedVersion' => $version, 'reason' => 'Reviewed provider subscriptions'];
    }

    private function register(string $id, int $version): string
    {
        $this->call('POST', '/api/payments/settings/yoco/webhooks', $this->admin, self::change($id, $version));

        return $this->body();
    }

    public function testAnInterruptedOperationCanBeClosedAfterProviderReviewWithoutMakingAnotherRegistration(): void
    {
        $this->saved();
        $this->loseResponse = true;
        $id = Uuid::v4()->toRfc4122();
        $this->register($id, 1);
        $this->connection->executeStatement("UPDATE payments_webhook_operations SET status = 'STARTED' WHERE id = ?", [$id]);
        $path = "/api/payments/settings/yoco/webhook-operations/$id/close";
        $evidence = ['expectedVersion' => 1, 'reason' => 'Inspected the provider subscription list after interruption'];
        $this->call('POST', $path, $this->admin, $evidence);
        self::assertSame(409, $this->code());
        $this->connection->executeStatement("UPDATE payments_webhook_operations SET created_at = now() - interval '6 minutes' WHERE id = ?", [$id]);
        $this->call('POST', $path, $this->admin, $evidence);
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame(1, $this->registrations);
        $this->call('GET', '/api/payments/settings/yoco/webhooks?mode=test', $this->admin);
        self::assertStringNotContainsString('STARTED', $this->body());
    }

    public function testRecoveryReasonsCannotPersistCredentialsInConfigurationHistory(): void
    {
        $this->saved();
        $this->loseResponse = true;
        $id = Uuid::v4()->toRfc4122();
        self::assertStringContainsString('UNKNOWN', $this->register($id, 1));
        $this->call('POST', "/api/payments/settings/yoco/webhook-operations/$id/close", $this->admin, ['expectedVersion' => 1, 'reason' => 'Reviewed sk_test_fixture']);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/payments/settings', $this->admin);
        self::assertStringNotContainsString('Reviewed sk_test_fixture', $this->body());
    }

    public function testCallbacksReferencedByExistingPaymentsCannotBeRemoved(): void
    {
        $this->saved();
        self::assertStringContainsString('APPLIED', $this->register(Uuid::v4()->toRfc4122(), 1));
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => 2, 'mode' => 'test']);
        self::assertStringContainsString('true', $this->body());
        $this->call('PUT', '/api/payments/settings/yoco', $this->admin, self::settings(2, true));
        self::assertSame(204, $this->code(), $this->body());
        $this->created('/api/payments', ['requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $this->person('Donor'), 'fundId' => $this->fund('General'), 'amountMinor' => 10000, 'provider' => 'yoco']);
        $this->call('PUT', '/api/payments/settings/yoco', $this->admin, self::settings(3, false));
        self::assertSame(204, $this->code(), $this->body());
        $this->call('POST', '/api/payments/settings/yoco/webhooks/sub_fixture/removal', $this->admin, self::change(Uuid::v4()->toRfc4122(), 4));
        self::assertSame(409, $this->code(), $this->body());
        self::assertCount(1, $this->subscriptions);
    }

    public function testRegistrationStoresTheSigningSecretWithoutReturningItAndRetriesDoNotCreateDuplicates(): void
    {
        $this->saved();
        $id = Uuid::v4()->toRfc4122();
        $registered = $this->register($id, 1);
        self::assertSame(200, $this->code(), $registered);
        self::assertStringContainsString('APPLIED', $registered);
        self::assertStringNotContainsString(self::SECRET, $registered);
        self::assertStringNotContainsString('sk_test_fixture', $registered);
        self::assertStringContainsString('APPLIED', $this->register($id, 2));
        self::assertSame([1, 2], [$this->registrations, $this->rows('payments_configuration_revisions')]);
        $encrypted = (string) $this->connection->fetchOne('SELECT encrypted_secret FROM payments_webhook_operations WHERE id = ?', [$id]);
        self::assertNotSame('', $encrypted);
        self::assertStringNotContainsString(self::SECRET, $encrypted);
        $this->call('GET', '/api/payments/settings/yoco/webhooks?mode=test', $this->admin);
        self::assertStringContainsString('https:\/\/api.church.example\/api\/payments\/webhooks\/yoco', $this->body());
        self::assertStringNotContainsString('encryptedSecret', $this->body());
        $this->call('POST', '/api/payments/settings/yoco/connection-check', $this->admin, ['expectedVersion' => 2, 'mode' => 'test']);
        self::assertStringContainsString('"successful":true', $this->body());
        $this->call('POST', '/api/payments/settings/yoco/webhooks', $this->admin, ['reason' => 'Different reason entirely'] + self::change($id, 2));
        self::assertStringContainsString('APPLIED', $this->body(), 'the reason is not part of the request identity');
    }

    public function testUncertainRegistrationNeverAutomaticallyRegistersAgain(): void
    {
        $this->saved();
        $this->loseResponse = true;
        $id = Uuid::v4()->toRfc4122();
        self::assertStringContainsString('UNKNOWN', $this->register($id, 1));
        self::assertStringContainsString('UNKNOWN', $this->register($id, 1));
        self::assertSame(1, $this->registrations);
        $this->call('GET', '/api/payments/settings/yoco/webhooks?mode=test', $this->admin);
        self::assertStringContainsString('sub_fixture', $this->body());
        self::assertStringContainsString('UNKNOWN', $this->body());
    }

    public function testAConcurrentSettingsChangeRetainsTheSecretForExplicitApplication(): void
    {
        $this->saved();
        $this->changeVersion = true;
        $id = Uuid::v4()->toRfc4122();
        self::assertStringContainsString('READY', $this->register($id, 1));
        $this->changeVersion = false;
        self::assertStringContainsString('APPLIED', $this->register($id, 2));
        self::assertSame(1, $this->registrations);
    }

    public function testObsoleteSubscriptionsCanBeRemovedWithIndependentConfigurationPermission(): void
    {
        $this->saved();
        $this->subscriptions[] = ['id' => 'sub_old', 'name' => 'Old tunnel', 'mode' => 'test', 'url' => 'https://old.example/api/payments/webhooks/yoco'];
        $this->call('POST', '/api/payments/settings/yoco/webhooks/sub_old/removal', $this->admin, self::change(Uuid::v4()->toRfc4122(), 1));
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame([], $this->subscriptions);
        $this->call('GET', '/api/payments/settings/yoco/webhooks?mode=test', null);
        self::assertSame(401, $this->code());
        $this->revoke('payments.configure');
        $this->call('GET', '/api/payments/settings/yoco/webhooks?mode=test', $this->admin);
        self::assertSame(403, $this->code());
    }
}
