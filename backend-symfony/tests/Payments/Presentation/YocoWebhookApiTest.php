<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Payments\Domain\Repository\PaymentRepository;
use App\Shared\Application\UnitOfWork;
use App\Tests\Payments\Support\PaymentApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Port of Java YocoWebhookApiIntegrationTest over real HTTP callbacks, signed with the bootstrap Yoco secret from
 * .env.test (live mode, deployment credentials): only a raw-body HMAC opens the ledger.
 */
final class YocoWebhookApiTest extends PaymentApiTestCase
{
    private static function notice(string $id, string $type, string $reference, int $amount): string
    {
        return '{"id":"evt_'.$reference.'","type":"'.$type.'","payload":{"id":"'.$reference.'","amount":'.$amount
            .',"currency":"ZAR","mode":"live","status":"succeeded","createdDate":"'.gmdate('Y-m-d\TH:i:s').'.123456Z","metadata":{"checkoutId":"co_one","orderId":"'.$id.'"}}}';
    }

    private function deliver(string $body, bool $valid, string $id = 'msg_one'): int
    {
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, 'test-webhook-secret-32-characters', true));
        $this->client->request('POST', '/api/payments/webhooks/yoco', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.($valid ? $signature : 'invalid'),
        ], content: $body);

        return $this->code();
    }

    public function testRawSignedHttpNotificationsAreRequiredAndDeduplicateGiftPosting(): void
    {
        $id = $this->order('yoco');
        self::assertSame('live', $this->connection->fetchOne('SELECT mode FROM payments_orders'));
        $repository = self::getContainer()->get(PaymentRepository::class);
        $transactions = self::getContainer()->get(UnitOfWork::class);
        \assert($repository instanceof PaymentRepository && $transactions instanceof UnitOfWork);
        $transactions->execute(static function () use ($repository, $id): void {
            $repository->lock($id);
            $repository->ready($id, 'co_one', 'https://c.yoco.com/checkout/one', '{}');
        });
        $paid = self::notice($id, 'payment.succeeded', 'payment_one', 10000);
        self::assertSame(400, $this->deliver($paid, false));
        self::assertSame(0, $this->rows('giving_gifts'));
        self::assertSame(204, $this->deliver($paid, true));
        self::assertSame(204, $this->deliver($paid, true));
        self::assertSame([1, 1], [$this->rows('giving_gifts'), $this->rows('payments_notifications')]);
        self::assertSame(204, $this->deliver(self::notice($id, 'refund.succeeded', 'refund_one', 2500), true));
        self::assertSame(1, $this->rows('giving_payment_refunds'));
        self::assertSame(400, $this->deliver(str_replace('"live"', '"test"', $paid), true), 'the environment must match');
    }

    public function testNotificationBeforeCheckoutConfirmationReturnsRetryableFailure(): void
    {
        $id = $this->order('yoco');
        self::assertSame(503, $this->deliver(self::notice($id, 'payment.succeeded', 'payment_early', 10000), true));
        self::assertSame([0, 0], [$this->rows('giving_gifts'), $this->rows('payments_notifications')]);
    }

    public function testUnknownOrdersAndIgnoredEventsWriteNothing(): void
    {
        self::assertSame(204, $this->deliver(self::notice(Uuid::v4()->toRfc4122(), 'payment.succeeded', 'nobody', 10000), true));
        self::assertSame(204, $this->deliver(str_replace('payment.succeeded', 'checkout.created', self::notice(Uuid::v4()->toRfc4122(), 'payment.succeeded', 'x', 1)), true));
        self::assertSame(0, $this->rows('payments_notifications'));
        $this->client->request('POST', '/api/payments/webhooks/yoco', server: ['CONTENT_TYPE' => 'application/json'], content: str_repeat('x', 65537));
        self::assertSame(400, $this->code());
    }
}
