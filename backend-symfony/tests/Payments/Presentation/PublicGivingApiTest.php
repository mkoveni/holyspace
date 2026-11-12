<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Communications\Application\DeliveryProcessor;
use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentProvider;
use App\Tests\Payments\Support\PaymentApiTestCase;
use App\Tests\Support\RecordingEmailTransport;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Public (guest) giving: an anonymous visitor gives once online with a compulsory email. No account, person or donor
 * link is involved; the verified payment posts an anonymous gift and an automatic confirmation email is sent.
 */
final class PublicGivingApiTest extends PaymentApiTestCase
{
    private string $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport()->reset();
        $this->transport()->enabled = true;
        $this->transport()->capture = true;
        $this->fund = $this->fund('Building '.Uuid::v4()->toRfc4122());
        $this->scriptGateway('live');
        $this->gateway()->providers = static fn (): array => [new PaymentProvider('payfast', 'Payfast', true, 'live', true), new PaymentProvider('yoco', 'Yoco', false, 'live', false)];
        $this->gateway()->checkout = static fn ($order): HostedCheckout => new HostedCheckout('checkout_'.$order->id, 'https://sandbox.payfast.co.za/eng/process', ['merchant_id' => 'fixture']);
        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code(), $this->body());
    }

    private function transport(): RecordingEmailTransport
    {
        $transport = self::getContainer()->get(RecordingEmailTransport::class);
        \assert($transport instanceof RecordingEmailTransport);

        return $transport;
    }

    private function processor(): DeliveryProcessor
    {
        $processor = self::getContainer()->get(DeliveryProcessor::class);
        \assert($processor instanceof DeliveryProcessor);

        return $processor;
    }

    /** @return array<string, mixed> */
    private function give(?string $id = null, string $email = 'giver@example.org', int $amount = 25000): array
    {
        return ['requestId' => $id ?? Uuid::v4()->toRfc4122(), 'fundId' => $this->fund, 'amountMinor' => $amount, 'provider' => 'payfast',
            'email' => $email, 'name' => 'Thandi Mabasa'];
    }

    /** @return array{0: string, 1: string} the order id and its access token */
    private function checkout(): array
    {
        $this->call('POST', '/api/public/giving/checkouts', null, $this->give());
        self::assertSame(201, $this->code(), $this->body());
        $json = $this->json();

        return [(string) $json['id'], (string) $json['accessToken']];
    }

    public function testOptionsListOnlyOnlineFundsAndConfiguredProvidersWithoutAnAccount(): void
    {
        $this->fund('Not online '.Uuid::v4()->toRfc4122());
        $this->call('GET', '/api/public/giving');
        self::assertSame(200, $this->code(), $this->body());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame([$this->fund], array_column($this->json()['funds'], 'id'));
        self::assertSame(['payfast'], array_column($this->json()['providers'], 'id'));
    }

    public function testAVisitorStartsACheckoutWithNoPersonAndTheEmailIsEncrypted(): void
    {
        $people = $this->rows('people_persons');
        [$id, $token] = $this->checkout();
        self::assertSame($people, $this->rows('people_persons'), 'no person is created');
        self::assertNotSame('', $token);
        $row = $this->connection->fetchAssociative('SELECT * FROM payments_orders WHERE id = ?', [$id]);
        self::assertIsArray($row);
        self::assertNull($row['donor_id']);
        self::assertSame('READY', $row['status']);
        self::assertSame('00000000-0000-0000-0000-0000000000a1', $row['created_by']);
        self::assertStringNotContainsString('giver@example.org', (string) $row['guest_email_enc']);
        self::assertNotSame($token, $row['guest_access_hash'], 'only a hash of the access token is stored');
        self::assertSame(1, $this->rows('payments_orders', "id = '$id' AND NOT recurring"));
    }

    public function testTheSameRequestReturnsTheSameOrderAndDifferentDetailsConflict(): void
    {
        $request = Uuid::v4()->toRfc4122();
        $this->call('POST', '/api/public/giving/checkouts', null, $this->give($request));
        $first = $this->json();
        $this->call('POST', '/api/public/giving/checkouts', null, $this->give($request));
        self::assertSame(201, $this->code(), $this->body());
        self::assertSame([$first['id'], $first['accessToken']], [$this->json()['id'], $this->json()['accessToken']]);
        self::assertSame(1, $this->rows('payments_orders', "id = '$request'"));
        $this->call('POST', '/api/public/giving/checkouts', null, $this->give($request, amount: 30000));
        self::assertSame(409, $this->code(), $this->body());
    }

    public function testEmailIsCompulsoryAndMustBeValid(): void
    {
        foreach ([null, '', 'not-an-email', "a@b.co\nbcc:x@y.z"] as $email) {
            $body = $this->give();
            $body['email'] = $email;
            $this->call('POST', '/api/public/giving/checkouts', null, $body);
            self::assertSame(400, $this->code(), 'email '.json_encode($email).': '.$this->body());
        }
        self::assertSame(0, $this->rows('payments_orders'));
    }

    public function testAmountFundAndProviderAreValidated(): void
    {
        $low = $this->give();
        $low['amountMinor'] = 499;
        $this->call('POST', '/api/public/giving/checkouts', null, $low);
        self::assertSame(400, $this->code());
        $other = $this->give();
        $other['fundId'] = $this->fund('Hidden '.Uuid::v4()->toRfc4122());
        $this->call('POST', '/api/public/giving/checkouts', null, $other);
        self::assertSame(400, $this->code(), 'a fund not offered online is refused');
        $monthly = $this->give();
        $monthly['recurring'] = true;
        $this->call('POST', '/api/public/giving/checkouts', null, $monthly);
        self::assertContains($this->code(), [201, 400], 'recurring is not part of the public request and is ignored');
        self::assertSame(0, $this->rows('payments_orders', 'recurring'));
    }

    public function testAFilledHoneypotSavesNothing(): void
    {
        $body = $this->give();
        $body['website'] = 'http://spam.example';
        $this->call('POST', '/api/public/giving/checkouts', null, $body);
        self::assertSame(201, $this->code());
        self::assertSame(0, $this->rows('payments_orders'));
        self::assertSame(0, $this->gateway()->checkouts);
    }

    public function testVerifiedPaymentPostsAnAnonymousGiftAndQueuesTheConfirmationOnce(): void
    {
        [$id, $token] = $this->checkout();
        self::assertSame(204, $this->event($id, 'PAID', 'charge_'.substr($id, 0, 8), 25000));
        self::assertSame(204, $this->event($id, 'PAID', 'charge_'.substr($id, 0, 8), 25000), 'a repeated notification changes nothing');

        $gift = $this->connection->fetchAssociative("SELECT g.* FROM giving_gifts g JOIN payments_charges c ON c.gift_id = g.id WHERE c.order_id = '$id'");
        self::assertIsArray($gift);
        self::assertNull($gift['donor_id'], 'the gift is anonymous');
        self::assertSame('ONLINE', $gift['method']);
        self::assertSame(25000, (int) $gift['amount_minor']);
        self::assertSame(1, $this->rows('payments_guest_confirmations'));
        self::assertSame(1, $this->rows('communication_email_deliveries'));

        $delivery = (string) $this->connection->fetchOne('SELECT delivery_id FROM payments_guest_confirmations');
        $this->processor()->dispatch($delivery);
        self::assertCount(1, $this->transport()->sent);
        self::assertSame('giver@example.org', $this->recipient());

        $this->call('POST', '/api/public/giving/orders/status', null, ['accessToken' => $token]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame(['PAID', true], [$this->json()['state'], $this->json()['confirmationQueued']]);
        self::assertArrayNotHasKey('email', $this->json());
    }

    private function recipient(): string
    {
        return (string) $this->connection->fetchOne('SELECT recipient FROM communication_email_deliveries LIMIT 1');
    }

    public function testASandboxPaymentPostsNoGiftAndSendsNoConfirmation(): void
    {
        $this->scriptGateway('test');
        $this->call('POST', '/api/public/giving/checkouts', null, $this->give());
        $id = (string) $this->json()['id'];
        self::assertSame(204, $this->event($id, 'PAID', 'charge_test_1', 25000, mode: 'test'));
        self::assertSame(0, $this->rows('giving_gifts'));
        self::assertSame(0, $this->rows('payments_guest_confirmations'));
    }

    public function testAConfirmationIsNotSentOnceTheChargeIsRefunded(): void
    {
        [$id] = $this->checkout();
        $this->event($id, 'PAID', 'charge_r1', 25000);
        $delivery = (string) $this->connection->fetchOne('SELECT delivery_id FROM payments_guest_confirmations');
        $this->event($id, 'REFUNDED', 'refund_r1', 25000);
        $this->processor()->dispatch($delivery);
        self::assertSame([], $this->transport()->sent);
    }

    public function testChangingTheStoredEmailBlocksTheFrozenConfirmation(): void
    {
        [$id] = $this->checkout();
        $this->event($id, 'PAID', 'charge_e1', 25000);
        $delivery = (string) $this->connection->fetchOne('SELECT delivery_id FROM payments_guest_confirmations');
        $this->connection->executeStatement('UPDATE communication_email_deliveries SET recipient = ? WHERE id = ?', ['attacker@example.org', $delivery]);
        $this->processor()->dispatch($delivery);
        self::assertSame([], $this->transport()->sent);
    }

    public function testStatusNeedsTheOrdersOwnTokenAndOtherTokensAreIndistinguishableFromUnknown(): void
    {
        [, $mine] = $this->checkout();
        [, $other] = $this->checkout();
        $this->call('POST', '/api/public/giving/orders/status', null, ['accessToken' => $mine]);
        self::assertSame(200, $this->code());
        $first = $this->json()['id'];
        $this->call('POST', '/api/public/giving/orders/status', null, ['accessToken' => $other]);
        self::assertNotSame($first, $this->json()['id']);
        foreach (['', 'nonsense', $first] as $bad) {
            $this->call('POST', '/api/public/giving/orders/status', null, ['accessToken' => $bad]);
            self::assertContains($this->code(), [400, 404], 'token '.$bad);
        }
    }

    public function testGuestOrdersAreUnreachableThroughTheAccountRoutes(): void
    {
        [$id] = $this->checkout();
        $account = $this->account('donor');
        $token = $this->login('donor');
        $this->call('PUT', "/api/giving/donor-access/$account", $this->admin, ['personId' => $this->person('Linked'), 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified account holder identity in person']);
        self::assertSame(204, $this->code(), $this->body());
        $this->call('POST', "/api/my-giving/payments/$id/checkout", $token);
        self::assertSame(404, $this->code(), $this->body());
        $this->call('GET', "/api/my-giving/payments/$id", $token);
        self::assertSame(404, $this->code(), $this->body());
    }

    public function testStaffSeeAPublicOrderWithoutAPersonName(): void
    {
        [$id] = $this->checkout();
        $this->call('GET', "/api/payments/$id", $this->admin);
        self::assertSame(200, $this->code(), $this->body());
        self::assertStringContainsString('Public giver', $this->body());
        self::assertStringNotContainsString('giver@example.org', $this->body());
    }

    public function testTheDatabaseRefusesAMalformedGuestOrder(): void
    {
        $this->expectException(Throwable::class);
        $this->connection->executeStatement(
            "INSERT INTO payments_orders (id, donor_id, fund_id, amount_minor, provider, mode, recurring, status, subscription_status, request_hash, created_by, created_at)
             VALUES (?, NULL, ?, 1000, 'payfast', 'live', FALSE, 'PENDING', 'NONE', 'h', ?, now())",
            [Uuid::v4()->toRfc4122(), $this->fund, $this->adminId],
        );
    }
}
