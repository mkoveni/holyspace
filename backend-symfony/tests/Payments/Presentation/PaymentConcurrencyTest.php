<?php

declare(strict_types=1);

namespace App\Tests\Payments\Presentation;

use App\Tests\Giving\Infrastructure\ParallelSession;
use App\Tests\Payments\Support\PaymentApiTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Java's `concurrentCallbacksDeduplicateByProviderReference` and two competing settlement reviews with two real
 * database sessions: this session handles a request and keeps its transaction open, a forked session sends the same
 * request from its own kernel and must wait on the order lock before deciding against the committed evidence.
 */
final class PaymentConcurrencyTest extends PaymentApiTestCase
{
    /** @return array{int, string} */
    private function contend(callable $first, callable $second): array
    {
        $this->connection->beginTransaction();
        $won = $first();
        $competitor = ParallelSession::start($second);
        $waited = $competitor->awaitsLock($this->connection);
        $this->connection->commit();
        $outcome = $competitor->outcome();
        self::assertTrue($waited, 'the second session must wait for the order lock; it finished with '.$outcome);

        return [$won, $outcome];
    }

    /**
     * @param array<string, string> $server
     *
     * @return callable(Connection): string
     */
    private static function remote(string $method, string $uri, array $server, string $content): callable
    {
        return static function (Connection $connection) use ($method, $uri, $server, $content): string {
            $kernel = self::createKernel();
            $kernel->boot();
            $kernel->getContainer()->set(Connection::class, $connection);
            $response = $kernel->handle(Request::create($uri, $method, server: $server, content: $content));

            return $response->getStatusCode().' '.$response->getContent();
        };
    }

    /** @return array<string, string> */
    private static function signed(string $body): array
    {
        $timestamp = (string) time();

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_WEBHOOK_ID' => 'msg_concurrent', 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.base64_encode(hash_hmac('sha256', 'msg_concurrent.'.$timestamp.'.'.$body, 'test-webhook-secret-32-characters', true))];
    }

    public function testConcurrentCallbacksDeduplicateByProviderReference(): void
    {
        $id = $this->order('yoco');
        $this->connection->executeStatement("UPDATE payments_orders SET checkout_id = 'co_one', status = 'READY', version = version + 1 WHERE id = ?", [$id]);
        $body = '{"id":"evt_same","type":"payment.succeeded","payload":{"id":"same","amount":10000,"currency":"ZAR","mode":"live","status":"succeeded","metadata":{"checkoutId":"co_one","orderId":"'.$id.'"}}}';
        $server = self::signed($body);

        [$won, $outcome] = $this->contend(function () use ($server, $body): int {
            $this->client->request('POST', '/api/payments/webhooks/yoco', server: $server, content: $body);

            return $this->code();
        }, self::remote('POST', '/api/payments/webhooks/yoco', $server, $body));

        self::assertSame(204, $won);
        self::assertStringStartsWith('ok:204', $outcome);
        self::assertSame([1, 1, 1], [$this->rows('giving_gifts'), $this->rows('payments_charges'), $this->rows('payments_notifications')]);
    }

    public function testTwoReviewsOfOneChargeKeepOneVersion(): void
    {
        $this->scriptGateway('live');
        $id = $this->order();
        $this->event($id, 'PAID', 'reviewed');
        $review = static fn (string $request, string $charge): string => json_encode(['requestId' => $request, 'chargeId' => $charge, 'expectedVersion' => 0,
            'settlementReference' => 'BANK-1', 'feeMinor' => 0, 'bankMinor' => 10000, 'note' => 'Matched'], \JSON_THROW_ON_ERROR);
        $charge = $this->chargeOf($id);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->admin];

        [$won, $outcome] = $this->contend(function () use ($id, $review, $charge, $server): int {
            $this->client->request('POST', "/api/payments/$id/reviews", server: $server, content: $review(Uuid::v4()->toRfc4122(), $charge));

            return $this->code();
        }, self::remote('POST', "/api/payments/$id/reviews", $server, $review(Uuid::v4()->toRfc4122(), $charge)));

        self::assertSame(201, $won);
        self::assertStringStartsWith('ok:409', $outcome);
        self::assertStringContainsString('Settlement review changed.', $outcome);
        self::assertSame(1, $this->rows('payments_reviews'));
    }
}
