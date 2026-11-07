<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Push;

use App\Communications\Application\Contract\PushTransport;
use App\Communications\Application\Contract\PushTransportResult;
use InvalidArgumentException;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Expo push over HTTPS (plain HTTP only to localhost). The payload is a generic nudge carrying the notice id, never its
 * content. Every failure maps to a state the processor understands; nothing here throws for a provider problem.
 */
final readonly class ExpoPushTransport implements PushTransport
{
    private const int MAX_RESPONSE_BYTES = 65536;

    private string $endpoint;

    public function __construct(
        private HttpClientInterface $http,
        string $endpoint,
        #[SensitiveParameter] private string $accessToken,
    ) {
        $parts = parse_url($endpoint);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        if ('https' !== $scheme && !('http' === $scheme && in_array($host, ['127.0.0.1', 'localhost'], true))) {
            throw new InvalidArgumentException('Use HTTPS for Expo push.');
        }
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function send(#[SensitiveParameter] string $token, string $notificationId): PushTransportResult
    {
        return $this->request('send', [
            'to' => $token,
            'title' => 'Holyspace',
            'body' => 'You have a new update in your inbox.',
            'sound' => 'default',
            'channelId' => 'member-updates',
            'ttl' => 3600,
            'data' => ['type' => 'holyspace.member-update.v1', 'notificationId' => $notificationId],
        ], null);
    }

    public function receipt(string $ticketId): PushTransportResult
    {
        return $this->request('getReceipts', ['ids' => [$ticketId]], $ticketId);
    }

    /** @param array<string, mixed> $body */
    private function request(string $operation, array $body, ?string $ticket): PushTransportResult
    {
        $receipt = null !== $ticket;
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        if ('' !== trim($this->accessToken)) {
            $headers['Authorization'] = 'Bearer '.$this->accessToken;
        }
        try {
            $response = $this->http->request('POST', $this->endpoint.'/'.$operation, [
                'headers' => $headers,
                'body' => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'timeout' => 10,
                'max_duration' => 15,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            if (429 === $status) {
                return new PushTransportResult('RETRY', $ticket, 'RATE_LIMITED');
            }
            if ($status >= 500) {
                return new PushTransportResult($receipt ? 'RETRY' : 'UNKNOWN', $ticket, 'PROVIDER_UNCERTAIN');
            }
            if (200 !== $status) {
                return new PushTransportResult('FAILED', $ticket, 'PROVIDER_REJECTED');
            }
            $payload = $response->getContent(false);
            if (strlen($payload) > self::MAX_RESPONSE_BYTES) {
                return new PushTransportResult($receipt ? 'PENDING' : 'UNKNOWN', $ticket, 'INVALID_RESPONSE');
            }
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return new PushTransportResult($receipt ? 'RETRY' : 'UNKNOWN', $ticket, 'TRANSPORT_UNCERTAIN');
        }

        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if ($receipt) {
            if (!is_array($data) || !array_key_exists((string) $ticket, $data)) {
                return new PushTransportResult('PENDING', $ticket, '');
            }
            $data = $data[(string) $ticket];
        }
        $data = is_array($data) ? $data : [];
        $state = is_string($data['status'] ?? null) ? $data['status'] : '';
        if ('ok' === $state) {
            if ($receipt) {
                return new PushTransportResult('PROVIDER_ACCEPTED', $ticket, '');
            }
            $id = is_string($data['id'] ?? null) ? $data['id'] : '';
            if (1 === preg_match('/\A[A-Za-z0-9_-]{1,200}\z/', $id)) {
                return new PushTransportResult('ACCEPTED', $id, '');
            }
        }
        $details = is_array($data['details'] ?? null) ? $data['details'] : [];
        $error = is_string($details['error'] ?? null) ? $details['error'] : '';
        if ('MessageRateExceeded' === $error) {
            return new PushTransportResult('RETRY', $ticket, $error);
        }
        if ('DeviceNotRegistered' === $error) {
            return new PushTransportResult('FAILED', $ticket, $error);
        }

        return new PushTransportResult('error' === $state ? 'FAILED' : ($receipt ? 'PENDING' : 'UNKNOWN'), $ticket, 'PROVIDER_REJECTED');
    }
}
