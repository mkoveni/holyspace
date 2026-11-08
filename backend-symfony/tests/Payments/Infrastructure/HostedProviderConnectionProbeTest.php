<?php

declare(strict_types=1);

namespace App\Tests\Payments\Infrastructure;

use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;
use App\Payments\Infrastructure\Gateway\HostedProviderConnectionProbe;
use App\Payments\Infrastructure\Gateway\HostedYocoWebhooks;
use App\Payments\Infrastructure\Gateway\SymfonyProviderHttp;
use App\Tests\Payments\Support\ScriptedProviderHttp;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Port of Java HostedProviderConnectionProbeTest, plus the Yoco webhook adapter and the HTTP adapter (MockHttpClient). */
final class HostedProviderConnectionProbeTest extends TestCase
{
    private static function configuration(string $provider): ProviderConfiguration
    {
        return new ProviderConfiguration(
            '77777777-7777-4777-8777-777777777777',
            $provider,
            'test',
            1,
            'https://church.example',
            '127.0.0.1/32',
            'encrypted',
            str_repeat('h', 64),
            true,
            true,
            true,
            true,
            true,
            '11111111-1111-4111-8111-111111111111',
            new DateTimeImmutable(),
            'https://api.church.example'
        );
    }

    private static function probe(?Closure $http): HostedProviderConnectionProbe
    {
        $provider = new ScriptedProviderHttp();
        $provider->handler = $http;

        return new HostedProviderConnectionProbe($provider, new MockClock());
    }

    public function testACallbackRegisteredOnTheFrontendDoesNotApproveTheBackendConnection(): void
    {
        $probe = self::probe(static fn (): string => '{"subscriptions":[{"url":"https://church.example/api/payments/webhooks/yoco","mode":"test"}]}');
        self::assertFalse($probe->check(self::configuration('yoco'), new ProviderCredentials(null, null, null, 'sk_test_fixture', 'whsec_fixture'))->successful);
    }

    public function testPayfastPingUsesAuthenticationWithoutCreatingOrChargingAnything(): void
    {
        $probe = self::probe(static function (string $method, string $url, array $headers, string $body): string {
            self::assertSame(['GET', 'https://api.payfast.co.za/ping?testing=true', ''], [$method, $url, $body]);
            self::assertSame('10000100', $headers['merchant-id']);
            self::assertArrayHasKey('signature', $headers);

            return 'API V1';
        });
        self::assertTrue($probe->check(self::configuration('payfast'), new ProviderCredentials('10000100', 'key', 'passphrase'))->successful);
    }

    public function testYocoChecksRegisteredCallbackAndEnvironmentWithoutWritingProviderData(): void
    {
        $probe = self::probe(static function (string $method, string $url): string {
            self::assertSame(['GET', 'https://payments.yoco.com/api/webhooks'], [$method, $url]);

            return '{"subscriptions":[{"url":"https://api.church.example/api/payments/webhooks/yoco","mode":"test"}]}';
        });
        self::assertTrue($probe->check(self::configuration('yoco'), new ProviderCredentials(null, null, null, 'sk_test_fixture', 'whsec_fixture'))->successful);
    }

    public function testNotificationNetworksMustBeExplicitAndRestricted(): void
    {
        $probe = self::probe(null);
        $probe->validateNetworks('197.97.145.144/28,41.74.179.192/27');
        foreach (['0.0.0.0/0', '0.0.0.0/00', 'example.org', '127.0.0.1,', '999.1.1.1/24', implode(',', array_fill(0, 31, '127.0.0.1'))] as $bad) {
            try {
                $probe->validateNetworks($bad);
                self::fail('Accepted '.$bad);
            } catch (PaymentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testYocoRegistrationIsConfirmedBeforeItsSecretIsTrusted(): void
    {
        $http = new ScriptedProviderHttp();
        $http->handler = static fn (string $method): string => 'POST' === $method
            ? '{"id":"sub_one","url":"https://api.church.example/api/payments/webhooks/yoco","mode":"test","secret":"whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="}'
            : '{"subscriptions":[{"id":"sub_one","name":"n","url":"u","mode":"test"}]}';
        $webhooks = new HostedYocoWebhooks($http);
        self::assertSame('sub_one', $webhooks->register('sk_test_fixture', 'name', 'https://api.church.example/api/payments/webhooks/yoco', 'test')->id);
        self::assertSame('sub_one', $webhooks->list('sk_test_fixture')[0]->id);
        $this->expectException(PaymentException::class);
        $webhooks->register('sk_test_fixture', 'name', 'https://elsewhere.example/api/payments/webhooks/yoco', 'test');
    }

    public function testHttpAdapterAcceptsOnlySuccessfulResponses(): void
    {
        $responses = [new MockResponse('VALID', ['http_code' => 200]), new MockResponse('nope', ['http_code' => 302, 'response_headers' => ['Location' => 'https://evil.test']]), new MockResponse('', ['error' => 'timeout'])];
        $http = new SymfonyProviderHttp(new MockHttpClient($responses));
        self::assertSame('VALID', $http->send('POST', 'https://sandbox.payfast.co.za/eng/query/validate', [], 'a=b'));
        foreach ([1, 2] as $attempt) {
            try {
                $http->send('GET', 'https://api.payfast.co.za/ping', [], '');
                self::fail('Accepted response '.$attempt);
            } catch (PaymentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
