<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Payments\Application\Contract\HostedCheckout;
use App\Tests\Payments\Support\PaymentApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Port of Java MobileCheckoutApiIntegrationTest: the one-time native-to-browser handoff. */
final class MobileCheckoutApiTest extends PaymentApiTestCase
{
    private string $account;
    private string $donor;
    private string $token;
    private string $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->account('mobile');
        $this->token = $this->login('mobile');
        $this->donor = $this->person('Mobile');
        $this->link($this->account, $this->donor, true, 0);
        $fund = $this->fund('General');
        $this->call('PUT', "/api/giving/online-funds/$fund", $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code(), $this->body());
        $this->scriptGateway('test');
        $this->gateway()->checkout = static fn (): HostedCheckout => new HostedCheckout('checkout', 'https://sandbox.payfast.co.za/eng/process', ['name_first' => "O'Reilly <safe>", 'merchant_id' => '10000100']);
        $this->order = $this->created('/api/my-giving/payments', ['requestId' => Uuid::v4()->toRfc4122(), 'fundId' => $fund, 'amountMinor' => 10000, 'provider' => 'payfast', 'recurring' => false], $this->token);
    }

    private function link(string $account, string $person, bool $active, int $version): void
    {
        $this->call('PUT', "/api/giving/donor-access/$account", $this->admin, ['personId' => $person, 'active' => $active, 'expectedVersion' => $version, 'evidence' => 'Verified donor']);
        self::assertSame(204, $this->code(), $this->body());
    }

    private function prepare(): string
    {
        $this->call('POST', "/api/my-giving/payments/{$this->order}/mobile-checkout", $this->token);
        self::assertSame(200, $this->code(), $this->body());
        foreach ([$this->token, 'merchant_id', 'encryptedCheckout'] as $secret) {
            self::assertStringNotContainsString($secret, $this->body());
        }
        $url = (string) $this->json()['url'];
        self::assertStringStartsWith('https://church.example/api/mobile-checkouts/', $url);

        return (string) parse_url($url, \PHP_URL_PATH);
    }

    private function open(string $path, string $method): int
    {
        $this->client->request($method, $path);

        return $this->code();
    }

    public function testBrowserLandingIsReadOnlyAndCompletingTheHandoffIsSingleUse(): void
    {
        $path = $this->prepare();
        self::assertSame(200, $this->open($path, 'GET'));
        self::assertSame(200, $this->open($path, 'GET'));
        self::assertSame(200, $this->open($path, 'POST'));
        $response = $this->client->getResponse();
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('form-action https://sandbox.payfast.co.za', (string) $response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('O&#39;Reilly &lt;safe&gt;', $this->body());
        self::assertStringContainsString('https://sandbox.payfast.co.za/eng/process', $this->body());
        self::assertStringNotContainsString($this->token, $this->body());
        self::assertSame(410, $this->open($path, 'POST'));
        self::assertSame(0, $this->rows('giving_gifts'));
        $sealed = (string) $this->connection->fetchOne('SELECT encrypted_checkout FROM payments_mobile_checkouts');
        self::assertStringNotContainsString('merchant_id', $sealed);
        self::assertStringNotContainsString("O'Reilly", $sealed);
        self::assertSame(1, $this->rows('payments_history', "action = 'MOBILE_CHECKOUT_OPENED'"));
    }

    public function testExpiredAndRevokedSessionCapabilitiesCannotOpenCheckout(): void
    {
        $path = $this->prepare();
        $this->connection->executeStatement("UPDATE payments_mobile_checkouts SET expires_at = now() - interval '1 minute'");
        self::assertSame(410, $this->open($path, 'POST'));
        $path = $this->prepare();
        $this->connection->executeStatement('DELETE FROM account_sessions WHERE account_id = ?', [$this->account]);
        self::assertSame(410, $this->open($path, 'POST'));
        self::assertSame(410, $this->open('/api/mobile-checkouts/short', 'POST'));
    }

    public function testRevokingDonorAccessAlsoInvalidatesPreviouslyPreparedHandoffs(): void
    {
        $path = $this->prepare();
        $this->link($this->account, $this->donor, false, 1);
        self::assertSame(410, $this->open($path, 'POST'));
    }

    public function testDonorCannotPrepareAnotherPersonsCheckoutAndYocoRedirectIsBounded(): void
    {
        $other = $this->account('other');
        $otherToken = $this->login('other');
        $this->link($other, $this->person('Other'), true, 0);
        $this->call('POST', "/api/my-giving/payments/{$this->order}/mobile-checkout", $otherToken);
        self::assertSame(404, $this->code());
        $this->gateway()->checkout = static fn (): HostedCheckout => new HostedCheckout('yoco', 'https://c.yoco.com/checkout/test', []);
        $path = $this->prepare();
        self::assertSame(303, $this->open($path, 'POST'));
        self::assertSame('https://c.yoco.com/checkout/test', $this->client->getResponse()->headers->get('Location'));
        self::assertSame(0, $this->rows('giving_gifts'));
    }
}
