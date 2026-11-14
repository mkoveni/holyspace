<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentProvider;
use App\Tests\Payments\Support\PaymentApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Port of Java DonorGivingApiIntegrationTest: donor self-service through the verified donor link only. */
final class DonorGivingApiTest extends PaymentApiTestCase
{
    private string $donor;
    private string $account;
    private string $fund;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->donor = $this->person('Donor');
        $this->account = $this->account('donor');
        $this->token = $this->login('donor');
        $this->fund = $this->fund('General');
        $this->scriptGateway('test');
        $this->gateway()->providers = static fn (): array => [new PaymentProvider('yoco', 'Yoco', true, 'test', false)];
        $this->gateway()->checkout = static fn (): HostedCheckout => new HostedCheckout('checkout', 'https://c.yoco.com/checkout/one', []);
    }

    private function link(bool $active, int $version, ?string $person = null, string $account = ''): int
    {
        $this->call(
            'PUT',
            '/api/giving/donor-access/'.('' === $account ? $this->account : $account),
            $this->admin,
            ['personId' => $person ?? $this->donor, 'active' => $active, 'expectedVersion' => $version, 'evidence' => 'Verified account holder identity in person']
        );

        return $this->code();
    }

    private function offer(bool $enabled, int $version): int
    {
        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->admin, ['enabled' => $enabled, 'expectedVersion' => $version]);

        return $this->code();
    }

    /** @return array<string, mixed> */
    private function start(string $id, bool $recurring = false): array
    {
        return ['requestId' => $id, 'fundId' => $this->fund, 'amountMinor' => 10000, 'provider' => 'yoco', 'recurring' => $recurring];
    }

    private function own(bool $monthly = false): string
    {
        return $this->created('/api/my-giving/payments', $this->start(Uuid::v4()->toRfc4122(), $monthly), $this->token);
    }

    private function ready(): void
    {
        self::assertSame(204, $this->link(true, 0));
        self::assertSame(204, $this->offer(true, 0));
    }

    public function testUnlinkedAccountsNeverGainFinancialAccessFromMatchingEmail(): void
    {
        $this->call('GET', '/api/my-giving', null);
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/my-giving', $this->token);
        self::assertSame(200, $this->code(), $this->body());
        self::assertNull($this->json()['donor']);
        $this->call('POST', '/api/my-giving/payments', $this->token, $this->start(Uuid::v4()->toRfc4122(), false));
        self::assertSame(403, $this->code());
        $this->call('GET', '/api/payments', $this->token);
        self::assertSame(403, $this->code());
        $this->call('PUT', "/api/giving/donor-access/{$this->account}", $this->token, ['personId' => $this->donor, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'self claim']);
        self::assertSame(403, $this->code());
    }

    public function testOnlineFundsRequireExplicitOptInAndCurrentVersion(): void
    {
        self::assertSame(204, $this->link(true, 0));
        $this->call('POST', '/api/my-giving/payments', $this->token, $this->start(Uuid::v4()->toRfc4122(), false));
        self::assertSame(400, $this->code(), $this->body());
        self::assertSame(204, $this->offer(true, 0));
        $this->own(false);
        self::assertSame(409, $this->offer(false, 0));
        self::assertSame(204, $this->offer(false, 1));
        $this->call('POST', '/api/my-giving/payments', $this->token, $this->start(Uuid::v4()->toRfc4122(), false));
        self::assertSame(400, $this->code());
    }

    public function testDonorIsResolvedServerSideAndRetryCannotDuplicateOrChangeIntent(): void
    {
        $this->ready();
        $id = Uuid::v4()->toRfc4122();
        self::assertSame($id, $this->created('/api/my-giving/payments', $this->start($id, false), $this->token));
        self::assertSame($id, $this->created('/api/my-giving/payments', $this->start($id, false), $this->token));
        self::assertSame([1, $this->donor, true], [$this->rows('payments_orders'), $this->connection->fetchOne('SELECT donor_id FROM payments_orders'), (bool) $this->connection->fetchOne('SELECT self_service FROM payments_orders')]);
        $this->call('POST', '/api/my-giving/payments', $this->token, ['amountMinor' => 20000] + $this->start($id, false));
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/my-giving/payments/$id/checkout", $this->token);
        self::assertSame(200, $this->code(), $this->body());
        $this->call('POST', "/api/my-giving/payments/$id/checkout", $this->token);
        self::assertSame(200, $this->code());
    }

    public function testAnotherDonorCannotReadCheckoutOfAnyonesOrder(): void
    {
        $this->ready();
        $id = $this->own(false);
        $second = $this->account('second');
        $other = $this->login('second');
        self::assertSame(204, $this->link(true, 0, $this->person('Other'), $second));
        $this->call('GET', "/api/my-giving/payments/$id", $other);
        self::assertSame(404, $this->code());
        $this->call('POST', "/api/my-giving/payments/$id/checkout", $other);
        self::assertSame(404, $this->code());
        $this->call('GET', '/api/my-giving', $other);
        self::assertStringNotContainsString($id, $this->body());
    }

    public function testVerifiedHistoryContainsOnlyOwnGiftsAndSafePaymentFields(): void
    {
        $this->ready();
        $this->scriptGateway('live');
        $this->gateway()->providers = static fn (): array => [new PaymentProvider('yoco', 'Yoco', true, 'live', false)];
        $id = $this->own(false);
        $this->event($id, 'PAID', 'charge_own');
        $this->call('POST', '/api/giving/gifts', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'amountMinor' => 5000, 'currency' => 'ZAR',
            'receivedOn' => $this->today()->iso(), 'method' => 'CASH', 'allocations' => [['fundId' => $this->fund, 'amountMinor' => 5000]]]);
        self::assertSame(201, $this->code(), $this->body());
        $this->call('GET', '/api/my-giving', $this->token);
        self::assertSame(200, $this->code(), $this->body());
        self::assertSame(1, $this->json()['giftTotal']);
        self::assertCount(1, $this->json()['providers'], 'only Yoco is offered');
        $this->call('GET', "/api/my-giving/payments/$id", $this->token);
        self::assertStringContainsString('POSTED', $this->body());
        self::assertStringContainsString('NOT_ISSUED', $this->body());
        foreach (['encryptedToken', 'checkoutFields', 'reviewReason', 'settlement', 'actorId', 'Awaiting settlement review'] as $hidden) {
            self::assertStringNotContainsString($hidden, $this->body());
        }
        self::assertSame(2, $this->rows('giving_gifts'));
    }

    public function testSimulationsRemainSeparateFromActualGivingHistory(): void
    {
        $this->ready();
        $id = $this->own(false);
        $this->event($id, 'PAID', 'sim', mode: 'test');
        $this->call('GET', "/api/my-giving/payments/$id", $this->token);
        self::assertStringContainsString('SIMULATION', $this->body());
        $this->call('GET', '/api/my-giving', $this->token);
        self::assertSame(0, $this->json()['giftTotal']);
    }

    public function testRevocationIsImmediateVersionedAndIdentityCannotBeReassigned(): void
    {
        $this->ready();
        $id = $this->own(false);
        self::assertSame(409, $this->link(true, 1, $this->person('Another')));
        self::assertSame(204, $this->link(false, 1));
        $this->call('GET', '/api/my-giving', $this->token);
        self::assertNull($this->json()['donor']);
        $this->call('POST', "/api/my-giving/payments/$id/checkout", $this->token);
        self::assertSame(403, $this->code());
        self::assertSame(204, $this->link(true, 2));
        $this->call('GET', "/api/my-giving/payments/$id", $this->token);
        self::assertSame(200, $this->code());
    }

    public function testDisabledAccountsAndRevokedSessionsCannotUseAnExistingLink(): void
    {
        $this->ready();
        $this->connection->executeStatement('DELETE FROM account_sessions WHERE account_id = ?', [$this->account]);
        $this->call('GET', '/api/my-giving', $this->token);
        self::assertSame(401, $this->code());
        $token = $this->login('donor');
        $this->connection->executeStatement("UPDATE user_accounts SET status = 'disabled' WHERE id = ?", [$this->account]);
        $this->call('GET', '/api/my-giving', $token);
        self::assertSame(401, $this->code());
    }

    public function testDonorCanViewFrozenConfirmationWithoutRecipientOrApprovalEvidence(): void
    {
        $this->ready();
        $this->scriptGateway('live');
        $id = $this->own(false);
        $this->event($id, 'PAID', 'charge_confirmed');
        $contact = $this->contact($this->donor, 'verified-donor@example.org');
        $charge = $this->chargeOf($id);
        $this->call('GET', "/api/payments/$id/receipts?chargeId=$charge&contactId=$contact", $this->admin);
        $hash = (string) $this->json()['preview']['hash'];
        $this->created("/api/payments/$id/receipts", ['requestId' => Uuid::v4()->toRfc4122(), 'chargeId' => $charge, 'contactId' => $contact, 'reviewHash' => $hash, 'evidence' => 'Private staff identity evidence']);
        $this->call('GET', "/api/my-giving/payments/$id", $this->token);
        self::assertSame(200, $this->code(), $this->body());
        foreach (['confirmation', 'Thank you', 'not a tax certificate', 'QUEUED'] as $shown) {
            self::assertStringContainsString($shown, $this->body());
        }
        foreach (['verified-donor@example.org', 'Private staff identity evidence', 'contactId', 'approvedBy'] as $hidden) {
            self::assertStringNotContainsString($hidden, $this->body());
        }
    }
}
