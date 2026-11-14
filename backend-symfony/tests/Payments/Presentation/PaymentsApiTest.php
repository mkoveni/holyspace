<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Payments\Domain\PaymentException;
use App\Tests\Payments\Support\PaymentApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Port of Java PaymentsApiIntegrationTest and PaymentPostingRecoveryIntegrationTest (scripted gateway, real Giving). */
final class PaymentsApiTest extends PaymentApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->scriptGateway('live');
        $this->gateway()->checkout = function ($order) {
            self::assertFalse($this->connection->isTransactionActive(), 'Checkout creation must run outside transactions');

            return new \App\Payments\Application\Contract\HostedCheckout('co_'.$order->id, 'https://c.yoco.com/checkout/one', []);
        };
    }

    private function ready(string $order): void
    {
        $this->call('POST', "/api/payments/$order/checkout", $this->admin);
        self::assertSame(200, $this->code(), $this->body());
    }

    private function detail(string $order): string
    {
        $this->call('GET', "/api/payments/$order", $this->admin);
        self::assertSame(200, $this->code(), $this->body());

        return $this->body();
    }

    public function testCheckoutIsDurableAndRetriesDoNotPostMoney(): void
    {
        $id = Uuid::v4()->toRfc4122();
        $body = ['requestId' => $id, 'donorId' => $this->person('Donor'), 'fundId' => $this->fund('General'), 'amountMinor' => 10000, 'provider' => 'yoco'];
        self::assertSame($id, $this->created('/api/payments', $body));
        self::assertSame($id, $this->created('/api/payments', $body));
        $this->call('POST', '/api/payments', $this->admin, ['amountMinor' => 12000] + $body);
        self::assertSame(409, $this->code());
        $this->ready($id);
        $this->ready($id);
        self::assertSame(1, $this->gateway()->checkouts, 'a confirmed checkout is reused, never created twice');
        self::assertSame([1, 0], [$this->rows('payments_orders'), $this->rows('giving_gifts')]);
        self::assertSame(1, $this->rows('payments_history', "action = 'CHECKOUT_READY'"));
    }

    public function testVerifiedLiveChargePostsExactlyOneGiftAndReturnQueryCannotAssertPayment(): void
    {
        $id = $this->order();
        $this->ready($id);
        $this->call('GET', "/api/payments/$id?status=paid", $this->admin);
        self::assertStringContainsString('READY', $this->body());
        self::assertSame(204, $this->event($id, 'PAID', 'charge_1'));
        self::assertSame(204, $this->event($id, 'PAID', 'charge_1'));
        self::assertSame([1, 1, 1], [$this->rows('giving_gifts'), $this->rows('payments_charges'), $this->rows('payments_notifications')]);
        self::assertFalse($this->gateway()->inTransaction, 'provider verification runs outside transactions');
        $gift = (string) $this->connection->fetchOne('SELECT id FROM giving_gifts');
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => $this->today()->iso(), 'reason' => 'Cannot bypass Payments']);
        self::assertSame(409, $this->code());
        self::assertStringContainsString('"givingStatus":"POSTED"', $this->detail($id));
    }

    public function testWrongAmountsAndConflictingNotificationsNeverPost(): void
    {
        $id = $this->order();
        self::assertSame(204, $this->event($id, 'PAID', 'wrong', 9000));
        self::assertSame(204, $this->event($id, 'PAID', 'wrong', 10000));
        self::assertSame(0, $this->rows('giving_gifts'));
        self::assertStringContainsString('conflicting', $this->detail($id));
    }

    public function testSandboxChargesNeverReachGiving(): void
    {
        $this->scriptGateway('test');
        $id = $this->order();
        self::assertSame(204, $this->event($id, 'PAID', 'sandbox', mode: 'test'));
        self::assertSame([1, 0], [$this->rows('payments_charges'), $this->rows('giving_gifts')]);
        $detail = $this->detail($id);
        self::assertStringContainsString('SIMULATION', $detail);
        self::assertStringContainsString('"needsReview":false', $detail);
    }

    public function testPartialRefundsAreDatedIdempotentAndCannotExceedCharge(): void
    {
        $id = $this->order();
        $this->event($id, 'PAID', 'charged');
        $this->event($id, 'REFUNDED', 'refund_1', 2500);
        $body = ['chargeId' => $this->chargeOf($id), 'providerReference' => 'refund_1', 'amountMinor' => 2500, 'refundedOn' => $this->today()->iso(), 'reason' => 'Completed in provider dashboard'];
        $this->call('POST', "/api/payments/$id/refunds", $this->admin, $body);
        self::assertSame(204, $this->code(), $this->body());
        $this->call('POST', "/api/payments/$id/refunds", $this->admin, $body);
        self::assertSame(204, $this->code(), $this->body());
        self::assertSame([1, 1], [$this->rows('payments_refunds'), $this->rows('giving_payment_refunds')]);
        $this->call('GET', "/api/giving/totals?from={$this->today()->iso()}&to={$this->today()->iso()}", $this->admin);
        self::assertSame('7500', (string) $this->json()[0]['netMinor']);
        $this->call('POST', "/api/payments/$id/refunds", $this->admin, ['providerReference' => 'refund_2', 'amountMinor' => 8000] + $body);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/payments/$id/refunds", $this->admin, ['providerReference' => 'refund_3', 'refundedOn' => $this->today()->plusDays(1)->iso()] + $body);
        self::assertSame(400, $this->code());
    }

    public function testRefundsBeforeChargesAreRetainedAndAppliedWhenMatched(): void
    {
        $id = $this->order();
        $this->event($id, 'REFUNDED', 'refund_late', 2000);
        self::assertSame(0, $this->rows('giving_payment_refunds'));
        $this->event($id, 'PAID', 'charge_late');
        self::assertSame(1, $this->rows('giving_payment_refunds'));
        self::assertStringContainsString('"applied":true', $this->detail($id));
    }

    public function testSettlementPreservesFeesAndBecomesStaleAfterRefund(): void
    {
        $id = $this->order();
        $this->event($id, 'PAID', 'charged');
        $review = Uuid::v4()->toRfc4122();
        $body = ['requestId' => $review, 'chargeId' => $this->chargeOf($id), 'expectedVersion' => 0, 'settlementReference' => 'BANK-001',
            'feeMinor' => 300, 'bankMinor' => 9700, 'note' => 'Matched bank and provider statement'];
        self::assertSame($review, $this->created("/api/payments/$id/reviews", $body));
        self::assertSame($review, $this->created("/api/payments/$id/reviews", $body));
        self::assertStringContainsString('"needsReview":false', $this->detail($id));
        $this->call('POST', "/api/payments/$id/reviews", $this->admin, ['requestId' => Uuid::v4()->toRfc4122()] + $body);
        self::assertSame(409, $this->code(), 'a stale review version is refused');
        $this->event($id, 'REFUNDED', 'refund_after', 1000);
        $detail = $this->detail($id);
        self::assertStringContainsString('"needsReview":true', $detail);
        self::assertStringContainsString('BANK-001', $detail);
    }

    public function testRevokedWritePermissionsDoNotInheritFromManagement(): void
    {
        $id = $this->order();
        $this->event($id, 'PAID', 'charged');
        $this->revoke('payments.refund');
        $this->call('POST', "/api/payments/$id/refunds", $this->admin, ['chargeId' => $this->chargeOf($id), 'providerReference' => 'r1', 'amountMinor' => 500, 'refundedOn' => $this->today()->iso(), 'reason' => 'Confirmed']);
        self::assertSame(403, $this->code());
        $this->revoke('payments.manage');
        $this->call('GET', '/api/payments', $this->admin);
        self::assertSame(403, $this->code());
        $this->call('GET', '/api/payments', null);
        self::assertSame(401, $this->code());
    }

    public function testInvalidSignaturesCannotEnterTheLedger(): void
    {
        $this->order();
        $this->gateway()->verify = static fn () => throw PaymentException::invalid('Invalid signature');
        $this->call('POST', '/api/payments/webhooks/yoco', null, []);
        self::assertSame(400, $this->code());
        self::assertSame([0, 0], [$this->rows('payments_notifications'), $this->rows('giving_gifts')]);
    }

    public function testCaseSensitiveProviderReferencesRetainDistinctRealCollections(): void
    {
        $id = $this->order();
        $this->event($id, 'PAID', 'charge_ABC');
        $this->event($id, 'PAID', 'charge_abc');
        self::assertSame(2, $this->rows('giving_gifts'));
        $this->event($id, 'FAILED', 'late_failure');
        self::assertStringContainsString('"status":"PAID"', $this->detail($id));
    }

    public function testExactCentsAndReadOnlyEndpointsAreEnforced(): void
    {
        $id = $this->order();
        $this->call('GET', '/api/payments?limit=101', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/payments', $this->admin);
        self::assertSame(1, $this->json()['total']);
        $this->detail($id);
        self::assertSame([0, 0], [$this->rows('payments_notifications'), $this->rows('giving_gifts')]);
        $this->call('POST', '/api/payments', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $this->person('Fraction'), 'fundId' => $this->fund('Other'), 'amountMinor' => 500.5, 'provider' => 'yoco']);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/payments/not-a-uuid', $this->admin);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/payments/'.Uuid::v4()->toRfc4122(), $this->admin);
        self::assertSame([404, 'Payment was not found.'], [$this->code(), $this->message()]);
    }

    public function testVerifiedChargeSurvivesLedgerFailureAndAuthorizedReconciliationRetriesIt(): void
    {
        $id = $this->order();
        $this->ledger()->failPosts = 1;
        self::assertSame(204, $this->event($id, 'PAID', 'one'));
        self::assertSame([1, 0], [$this->rows('payments_charges'), $this->rows('giving_gifts')]);
        $detail = $this->detail($id);
        self::assertStringContainsString('needs ledger posting', $detail);
        self::assertStringContainsString('POSTING_PENDING', $detail);
        $this->created("/api/payments/$id/reviews", ['requestId' => Uuid::v4()->toRfc4122(), 'chargeId' => $this->chargeOf($id), 'expectedVersion' => 0,
            'settlementReference' => 'BANK-RECOVERED', 'feeMinor' => 0, 'bankMinor' => 10000, 'note' => 'Provider confirms payment; retried ledger posting']);
        self::assertSame([1, 1], [$this->rows('giving_gifts'), $this->rows('payments_charges')]);
        self::assertStringContainsString('"reviewReason":null', $this->detail($id));
    }

    public function testFailedPostingCanBeRetriedWithoutAnotherCheckoutAndNeverDuplicatesGiving(): void
    {
        $id = $this->order();
        $this->ledger()->failPosts = 1;
        $this->event($id, 'PAID', 'retry');
        self::assertSame(0, $this->rows('giving_gifts'));
        $this->call('POST', "/api/payments/$id/posting-retries", $this->admin, ['expectedVersion' => $this->version($id)]);
        self::assertSame(200, $this->code(), $this->body());
        self::assertTrue($this->json()['complete']);
        $this->call('POST', "/api/payments/$id/posting-retries", $this->admin, ['expectedVersion' => $this->version($id)]);
        self::assertSame(200, $this->code());
        self::assertSame(1, $this->rows('giving_gifts'));
        self::assertSame(0, $this->gateway()->checkouts);
        $detail = $this->detail($id);
        self::assertStringContainsString('POSTED', $detail);
        self::assertStringContainsString('NEEDS_REVIEW', $detail);
        $this->call('POST', "/api/payments/$id/posting-retries", $this->admin, ['expectedVersion' => 0]);
        self::assertSame(409, $this->code(), 'a stale order version is refused');
    }

    public function testSimulationsCannotBePostedToGiving(): void
    {
        $this->scriptGateway('test');
        $id = $this->order();
        $this->event($id, 'PAID', 'sim', mode: 'test');
        $this->call('POST', "/api/payments/$id/posting-retries", $this->admin, ['expectedVersion' => $this->version($id)]);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/payments/$id/reviews", $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'chargeId' => $this->chargeOf($id), 'expectedVersion' => 0,
            'settlementReference' => 'X', 'feeMinor' => 0, 'bankMinor' => 10000, 'note' => 'n']);
        self::assertSame([409, 'Simulation payments have no real bank settlement to review.'], [$this->code(), $this->message()]);
    }
}
