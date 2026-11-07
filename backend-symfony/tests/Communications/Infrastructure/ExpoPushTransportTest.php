<?php

declare(strict_types=1);

namespace App\Tests\Communications\Infrastructure;

use App\Communications\Application\Contract\PushTransportResult;
use App\Communications\Infrastructure\Push\ExpoPushTransport;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Port of Java `ExpoPushTransport` behaviour: every provider answer maps to a state; nothing throws. */
final class ExpoPushTransportTest extends TestCase
{
    private const string URL = 'https://exp.test/--/api/v2/push';

    private static function transport(MockResponse|callable $response, string $token = ''): ExpoPushTransport
    {
        return new ExpoPushTransport(new MockHttpClient($response), self::URL, $token);
    }

    public function testSendPostsAGenericNudgeWithTheNoticeIdAndReturnsTheTicket(): void
    {
        $request = '';
        $sent = '';
        $headers = [];
        $transport = self::transport(static function (string $method, string $url, array $options) use (&$request, &$sent, &$headers): MockResponse {
            $request = "$method $url";
            $sent = (string) $options['body'];
            $headers = (array) $options['headers'];

            return new MockResponse('{"data":{"status":"ok","id":"ticket_ABC-1"}}');
        }, 'expo-access');

        $result = $transport->send('ExpoPushToken[abcdefghij]', 'n-1');

        self::assertEquals(new PushTransportResult('ACCEPTED', 'ticket_ABC-1', ''), $result);
        self::assertSame('POST '.self::URL.'/send', $request);
        $body = json_decode($sent, true);
        self::assertSame([
            'to' => 'ExpoPushToken[abcdefghij]',
            'title' => 'Holyspace',
            'body' => 'You have a new update in your inbox.',
            'sound' => 'default',
            'channelId' => 'member-updates',
            'ttl' => 3600,
            'data' => ['type' => 'holyspace.member-update.v1', 'notificationId' => 'n-1'],
        ], $body);
        self::assertContains('Authorization: Bearer expo-access', $headers);
    }

    public function testNoAuthorizationHeaderWithoutAnAccessToken(): void
    {
        $headers = [];
        self::transport(static function (string $method, string $url, array $options) use (&$headers): MockResponse {
            $headers = $options['headers'];

            return new MockResponse('{"data":{"status":"ok","id":"t"}}');
        })->send('ExpoPushToken[abcdefghij]', 'n-1');

        self::assertSame([], array_values(array_filter($headers, static fn (string $h): bool => str_starts_with($h, 'Authorization'))));
    }

    /** @return array<string, array{MockResponse, string, ?string, string}> */
    public static function sendOutcomes(): array
    {
        return [
            'rate limited' => [new MockResponse('', ['http_code' => 429]), 'RETRY', null, 'RATE_LIMITED'],
            'provider down' => [new MockResponse('', ['http_code' => 503]), 'UNKNOWN', null, 'PROVIDER_UNCERTAIN'],
            'rejected' => [new MockResponse('', ['http_code' => 400]), 'FAILED', null, 'PROVIDER_REJECTED'],
            'not json' => [new MockResponse('nope'), 'UNKNOWN', null, 'TRANSPORT_UNCERTAIN'],
            'too large' => [new MockResponse(str_repeat(' ', 65537)), 'UNKNOWN', null, 'INVALID_RESPONSE'],
            'bad ticket id' => [new MockResponse('{"data":{"status":"ok","id":"bad id!"}}'), 'UNKNOWN', null, 'PROVIDER_REJECTED'],
            'message rate' => [new MockResponse('{"data":{"status":"error","details":{"error":"MessageRateExceeded"}}}'), 'RETRY', null, 'MessageRateExceeded'],
            'unregistered' => [new MockResponse('{"data":{"status":"error","details":{"error":"DeviceNotRegistered"}}}'), 'FAILED', null, 'DeviceNotRegistered'],
            'other error' => [new MockResponse('{"data":{"status":"error","details":{"error":"InvalidCredentials"}}}'), 'FAILED', null, 'PROVIDER_REJECTED'],
            'connection failed' => [new MockResponse('', ['error' => 'connection refused']), 'UNKNOWN', null, 'TRANSPORT_UNCERTAIN'],
        ];
    }

    #[DataProvider('sendOutcomes')]
    public function testSendOutcomes(MockResponse $response, string $state, ?string $ticket, string $error): void
    {
        self::assertEquals(new PushTransportResult($state, $ticket, $error), self::transport($response)->send('ExpoPushToken[abcdefghij]', 'n-1'));
    }

    /** @return array<string, array{MockResponse, string, string}> */
    public static function receiptOutcomes(): array
    {
        return [
            'accepted' => [new MockResponse('{"data":{"t-1":{"status":"ok"}}}'), 'PROVIDER_ACCEPTED', ''],
            'not ready' => [new MockResponse('{"data":{}}'), 'PENDING', ''],
            'provider down' => [new MockResponse('', ['http_code' => 502]), 'RETRY', 'PROVIDER_UNCERTAIN'],
            'unregistered' => [new MockResponse('{"data":{"t-1":{"status":"error","details":{"error":"DeviceNotRegistered"}}}}'), 'FAILED', 'DeviceNotRegistered'],
            'odd status' => [new MockResponse('{"data":{"t-1":{"status":"queued"}}}'), 'PENDING', 'PROVIDER_REJECTED'],
            'too large' => [new MockResponse(str_repeat(' ', 65537)), 'PENDING', 'INVALID_RESPONSE'],
            'connection failed' => [new MockResponse('', ['error' => 'timeout']), 'RETRY', 'TRANSPORT_UNCERTAIN'],
        ];
    }

    #[DataProvider('receiptOutcomes')]
    public function testReceiptOutcomes(MockResponse $response, string $state, string $error): void
    {
        self::assertEquals(new PushTransportResult($state, 't-1', $error), self::transport($response)->receipt('t-1'));
    }

    public function testTheReceiptRequestNamesOnlyItsTicket(): void
    {
        $seen = [];
        self::transport(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [$url, json_decode((string) $options['body'], true)];

            return new MockResponse('{"data":{}}');
        })->receipt('t-1');

        self::assertSame([self::URL.'/getReceipts', ['ids' => ['t-1']]], $seen);
    }

    public function testOnlyHttpsOrLocalHttpEndpointsAreAccepted(): void
    {
        new ExpoPushTransport(new MockHttpClient(), 'http://localhost:8080/push', '');
        new ExpoPushTransport(new MockHttpClient(), 'http://127.0.0.1/push', '');
        foreach (['http://exp.host/--/api/v2/push', 'ftp://exp.host/push', 'not a url'] as $url) {
            try {
                new ExpoPushTransport(new MockHttpClient(), $url, '');
                self::fail("$url must be refused");
            } catch (InvalidArgumentException $expected) {
                self::assertSame('Use HTTPS for Expo push.', $expected->getMessage());
            }
        }
    }

    public function testAnExceptionFromTheClientIsUncertain(): void
    {
        $transport = self::transport(static fn (): MockResponse => throw new RuntimeException('boom'));

        self::assertSame(['UNKNOWN', 'TRANSPORT_UNCERTAIN'], [$transport->send('ExpoPushToken[abcdefghij]', 'n')->state, $transport->send('ExpoPushToken[abcdefghij]', 'n')->errorCode]);
        self::assertSame('RETRY', $transport->receipt('t-1')->state);
    }
}
