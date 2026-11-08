<?php

declare(strict_types=1);

namespace App\Tests\Payments\Infrastructure;

use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Infrastructure\Gateway\AesSubscriptionVault;
use App\Payments\Infrastructure\Gateway\GatewaySettings;
use App\Payments\Infrastructure\Gateway\HostedPaymentGateway;
use App\Payments\Infrastructure\Gateway\IpRange;
use App\Payments\Infrastructure\Gateway\JavaTimestamp;
use App\Payments\Infrastructure\Gateway\ProviderForms;
use App\Shared\Application\ChurchCalendar;
use App\Tests\Payments\Support\FixedSettingsSource;
use App\Tests\Payments\Support\ScriptedProviderHttp;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Port of Java HostedPaymentGatewayTest, plus a Java-captured signed checkout (PaymentVectors.java). No network. */
final class HostedPaymentGatewayTest extends TestCase
{
    private const string NOW = '2026-10-28T10:00:00Z';

    private static function secret(): string
    {
        return base64_encode('test-webhook-secret-32-characters');
    }

    private static function settings(string $passphrase = 'passphrase', string $merchantKey = 'merchant-key'): GatewaySettings
    {
        return new GatewaySettings(
            'https://church.example',
            'test',
            '10000100',
            $merchantKey,
            $passphrase,
            '127.0.0.1/32',
            'test',
            'sk_test_example',
            'whsec_'.self::secret(),
            base64_encode(str_repeat("\0", 32)),
            'https://api.church.example'
        );
    }

    private static function gateway(?Closure $http = null, string $now = self::NOW, ?GatewaySettings $settings = null): HostedPaymentGateway
    {
        $provider = new ScriptedProviderHttp();
        $provider->handler = $http;
        $clock = new MockClock(new DateTimeImmutable($now));

        return new HostedPaymentGateway(new FixedSettingsSource($settings ?? self::settings()), $provider, $clock, new ChurchCalendar($clock, 'Africa/Johannesburg'));
    }

    private static function order(bool $recurring, string $provider = 'payfast', bool $self = false, string $id = '44444444-4444-4444-8444-444444444444', int $amount = 10000): PaymentOrder
    {
        return PaymentOrder::start(
            $id,
            '22222222-2222-4222-8222-222222222222',
            '33333333-3333-4333-8333-333333333333',
            $amount,
            $provider,
            'test',
            $recurring,
            str_repeat('h', 64),
            '11111111-1111-4111-8111-111111111111',
            new DateTimeImmutable(self::NOW),
            null,
            $self
        );
    }

    private static function event(): string
    {
        return '{"id":"evt_one","type":"payment.succeeded","payload":{"id":"p_one","amount":10000,"currency":"ZAR","mode":"test","status":"succeeded","createdDate":"2026-10-28T09:00:00Z","metadata":{"checkoutId":"co_one"}}}';
    }

    /** @return array<string, string> */
    private static function headers(string $body, int $timestamp): array
    {
        $signature = base64_encode(hash_hmac('sha256', 'msg_one.'.$timestamp.'.'.$body, 'test-webhook-secret-32-characters', true));

        return ['webhook-id' => 'msg_one', 'webhook-timestamp' => (string) $timestamp, 'webhook-signature' => 'v1,invalid v1,'.$signature];
    }

    private function rejects(callable $call): void
    {
        try {
            $call();
            self::fail('Expected a PaymentException');
        } catch (PaymentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testDonorCheckoutReturnsToMyGivingAndKeepsBackendCallbacksSeparate(): void
    {
        $checkout = self::gateway()->checkout(self::order(false, self: true), 'Donor', 'donor@example.org');
        self::assertSame('https://church.example/my-giving?order=44444444-4444-4444-8444-444444444444', $checkout->fields['return_url']);
        self::assertSame('https://api.church.example/api/payments/webhooks/payfast', $checkout->fields['notify_url']);
    }

    public function testMatchesTheJavaSignedMonthlyCheckout(): void
    {
        $settings = new GatewaySettings(
            'https://church.example',
            'test',
            '10000100',
            'merchant-key',
            'pass phrase*~',
            '127.0.0.1/32',
            'test',
            'sk_test_example',
            'whsec_x',
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            'https://api.church.example'
        );
        $checkout = self::gateway(null, '2026-10-28T22:30:00Z', $settings)->checkout(self::order(true, self: true, amount: 12345), "Thandi O'Neil & Co", 'donor@example.org');
        self::assertSame([
            'merchant_id' => '10000100', 'merchant_key' => 'merchant-key',
            'return_url' => 'https://church.example/my-giving?order=44444444-4444-4444-8444-444444444444',
            'cancel_url' => 'https://church.example/my-giving?order=44444444-4444-4444-8444-444444444444&returned=cancelled',
            'notify_url' => 'https://api.church.example/api/payments/webhooks/payfast', 'name_first' => "Thandi O'Neil & Co",
            'email_address' => 'donor@example.org', 'm_payment_id' => '44444444-4444-4444-8444-444444444444', 'amount' => '123.45',
            'item_name' => 'Church giving', 'subscription_type' => '1', 'billing_date' => '2026-11-29', 'recurring_amount' => '123.45',
            'frequency' => '3', 'cycles' => '0', 'signature' => '656d444642ba952a1eaa5b916c72616f',
        ], $checkout->fields);
    }

    public function testYocoReturnsTheBrowserToTheFrontendWhenTheBackendHasADifferentOrigin(): void
    {
        $gateway = self::gateway(static function (string $method, string $url, array $headers, string $body): string {
            self::assertSame(['POST', 'https://payments.yoco.com/api/checkouts'], [$method, $url]);
            self::assertStringContainsString('https://church.example/payments?order=44444444-4444-4444-8444-444444444444', $body);
            self::assertStringNotContainsString('https://api.church.example', $body);
            self::assertSame('44444444-4444-4444-8444-444444444444', $headers['Idempotency-Key']);

            return '{"id":"co_test","amount":10000,"currency":"ZAR","processingMode":"test","redirectUrl":"https://c.yoco.com/checkout/test"}';
        });
        $checkout = $gateway->checkout(self::order(false, 'yoco'), 'Donor', 'donor@example.org');
        self::assertSame(['co_test', 'https://c.yoco.com/checkout/test', []], [$checkout->id, $checkout->url, $checkout->fields]);
        $this->rejects(fn () => self::gateway(static fn (): string => '{"id":"co_test","amount":9000,"currency":"ZAR","processingMode":"test","redirectUrl":"https://c.yoco.com/x"}')
            ->checkout(self::order(false, 'yoco'), 'Donor', ''));
    }

    public function testVerifiesRawBodyAndTimestampBeforeParsing(): void
    {
        $gateway = self::gateway();
        $time = new DateTimeImmutable(self::NOW)->getTimestamp();
        $body = self::event();
        $notification = $gateway->verify('yoco', $body, self::headers($body, $time), '127.0.0.1');
        self::assertNotNull($notification);
        self::assertSame([10000, 'PAID', 'co_one', '2026-10-28'], [$notification->amountMinor, $notification->type, $notification->checkoutId, $notification->date?->iso()]);
        $this->rejects(fn () => $gateway->verify('yoco', $body.' ', self::headers($body, $time), '127.0.0.1'));
        $this->rejects(fn () => $gateway->verify('yoco', $body, self::headers($body, $time - 181), '127.0.0.1'));
    }

    public function testRejectsModeCurrencyStatusAndFractionalAmountTampering(): void
    {
        $gateway = self::gateway(static fn (): string => '');
        $time = new DateTimeImmutable(self::NOW)->getTimestamp();
        foreach ([str_replace('10000', '10000.5', self::event()), str_replace('"test"', '"live"', self::event()), str_replace('"succeeded"', '"failed"', self::event())] as $changed) {
            $this->rejects(fn () => $gateway->verify('yoco', $changed, self::headers($changed, $time), '127.0.0.1'));
        }
    }

    public function testPayfastUsesSignedOrderedFormAndIndependentServerValidation(): void
    {
        $values = ['merchant_id' => '10000100', 'm_payment_id' => '66666666-6666-4666-8666-666666666666', 'pf_payment_id' => '12345',
            'payment_status' => 'COMPLETE', 'amount_gross' => '100.00', 'item_description' => ''];
        $encoded = ProviderForms::form($values);
        $body = $encoded.'&signature='.md5($encoded.'&passphrase=passphrase');
        $gateway = self::gateway(static function (string $method, string $url, array $headers, string $sent) use ($encoded): string {
            self::assertSame(['POST', 'https://sandbox.payfast.co.za/eng/query/validate', $encoded], [$method, $url, $sent]);

            return 'VALID';
        });
        $notification = $gateway->verify('payfast', $body, [], '127.0.0.1');
        self::assertSame(['12345', '12345:PAID', 10000, '66666666-6666-4666-8666-666666666666'], [$notification?->reference, $notification?->eventId, $notification?->amountMinor, $notification?->orderId]);
        $this->rejects(fn () => $gateway->verify('payfast', $body, [], '192.0.2.1'));
        $this->rejects(fn () => self::gateway(static fn (): string => 'INVALID')->verify('payfast', $body, [], '127.0.0.1'));
        $this->rejects(fn () => $gateway->verify('payfast', str_replace('100.00', '1000.00', $body), [], '127.0.0.1'));
        $this->rejects(fn () => $gateway->verify('payfast', $body.'&merchant_id=10000100', [], '127.0.0.1'));
    }

    public function testMonthlyCheckoutUsesNextMonthAndKeepsPassphrasePrivate(): void
    {
        $checkout = self::gateway()->checkout(self::order(true), 'Donor', 'donor@example.org');
        self::assertSame('https://church.example/payments?order='.$checkout->fields['m_payment_id'], $checkout->fields['return_url']);
        self::assertSame(['2026-11-28', '3', '100.00'], [$checkout->fields['billing_date'], $checkout->fields['frequency'], $checkout->fields['amount']]);
        self::assertArrayNotHasKey('passphrase', $checkout->fields);
        self::assertSame('signature', array_key_last($checkout->fields));
        self::assertSame('https://sandbox.payfast.co.za/eng/process', $checkout->url);
    }

    public function testSubscriptionsUseSignedApiCallsAndOnlyConfirmedStatuses(): void
    {
        $token = '12345678-1234-1234-1234-123456789012';
        $gateway = self::gateway(static function (string $method, string $url, array $headers) use ($token): string {
            self::assertSame(['PUT', 'https://api.payfast.co.za/subscriptions/'.$token.'/cancel?testing=true'], [$method, $url]);
            self::assertSame('2026-10-28T10:00Z', $headers['timestamp']);
            self::assertSame(md5('merchant-id=10000100&passphrase=passphrase&timestamp=2026-10-28T10%3A00Z&version=v1'), $headers['signature']);

            return '{"status":"success","code":200,"data":{"response":true}}';
        });
        self::assertSame('CANCELLED', $gateway->subscription(self::order(true), $token, true));
        $this->rejects(fn () => self::gateway(static fn (): string => '{"status":"success","code":200,"data":{"response":{"status_text":"weird"}}}')->subscription(self::order(true), $token, false));
        self::assertSame('PAUSED', self::gateway(static fn (): string => '{"status":"success","code":200,"data":{"response":{"status_text":"paused"}}}')->subscription(self::order(true), $token, false));
        $this->rejects(fn () => $gateway->subscription(self::order(true), 'not-a-token', true));
    }

    public function testCheckoutAddressesCannotEscapeProviderHost(): void
    {
        self::assertTrue(HostedPaymentGateway::validYocoUrl('https://c.yoco.com/checkout/one'));
        foreach (['http://c.yoco.com/one', 'https://c.yoco.com.evil.test/one', 'https://evil.test@c.yoco.com/one', 'https://c.yoco.com:444/one', 'https://c.yoco.com/a b'] as $value) {
            self::assertFalse(HostedPaymentGateway::validYocoUrl($value), $value);
        }
    }

    public function testIpRangesAndJavaTimestamps(): void
    {
        $range = IpRange::parse('197.97.145.144/28');
        $loopback = IpRange::parse('127.0.0.1/32');
        $v6 = IpRange::parse('2001:db8::/32');
        self::assertNotNull($range);
        self::assertNotNull($loopback);
        self::assertNotNull($v6);
        self::assertTrue($range->matches('197.97.145.150'));
        self::assertFalse($range->matches('197.97.145.160'));
        self::assertFalse($loopback->matches('::1'));
        self::assertTrue($v6->matches('2001:db8::1'));
        self::assertNull(IpRange::parse('999.1.1.1/24'));
        self::assertNull(IpRange::parse('127.0.0.1/33'));
        self::assertSame('2026-11-08T10:15:30.123456Z', JavaTimestamp::utc(new DateTimeImmutable('2026-11-08T12:15:30.123456+02:00')));
        self::assertSame('2026-11-08T10:15:30.120Z', JavaTimestamp::utc(new DateTimeImmutable('2026-11-08T10:15:30.12Z')));
        self::assertSame('2026-11-08T10:15:05Z', JavaTimestamp::utc(new DateTimeImmutable('2026-11-08T10:15:05Z')));
    }

    public function testTokensAreAuthenticatedEncryptedAndRedacted(): void
    {
        $vault = new AesSubscriptionVault(base64_encode(str_repeat("\0", 32)));
        $token = '12345678-1234-1234-1234-123456789012';
        self::assertSame($token, $vault->decrypt($vault->encrypt($token)));
        self::assertStringNotContainsString('merchant-key', print_r(self::settings(), true));
        self::assertStringNotContainsString('encryptedToken', print_r(self::order(true), true));
    }
}
