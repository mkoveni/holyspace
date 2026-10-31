<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Port\PaymentGateway;
use App\Payments\Application\Port\ProviderCatalogue;
use App\Payments\Domain\Model\PaymentOrder;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Exception;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsAlias(PaymentGateway::class)]
#[AsAlias(ProviderCatalogue::class)]
final readonly class HostedPaymentGateway implements PaymentGateway
{
    public function __construct(
        private PaymentConfiguration $config,
        private HttpClientInterface $http,
        private ClockInterface $clock,
    ) {
    }

    public function providers(): array
    {
        return array_map(fn ($provider) => ['id' => $provider, 'name' => 'payfast' === $provider ? 'Payfast' : 'Yoco', 'available' => $this->config->ready($provider), 'mode' => 'payfast' === $provider ? $this->config->payfastMode : $this->config->yocoMode, 'recurring' => 'payfast' === $provider], ['payfast', 'yoco']);
    }

    public function mode(string $provider): string
    {
        if (!$this->config->ready($provider)) {
            throw new DomainException('This payment provider is not configured. Ask church staff.');
        }

        return 'payfast' === $provider ? $this->config->payfastMode : $this->config->yocoMode;
    }

    public function checkout(PaymentOrder $order, string $name, string $email): array
    {
        if ($this->mode($order->provider) !== $order->mode) {
            throw new DomainException('Payment configuration changed. Start a new checkout.');
        }
        $return = $this->config->baseUrl().'/member/payments?order='.$order->id;
        if ('payfast' === $order->provider) {
            $fields = ['merchant_id' => $this->config->merchantId, 'merchant_key' => $this->config->merchantKey, 'return_url' => $return, 'cancel_url' => $return.'&returned=cancelled', 'notify_url' => $this->config->baseUrl().'/api/payments/webhooks/payfast', 'name_first' => mb_substr($name, 0, 100), 'email_address' => $email, 'm_payment_id' => $order->id, 'amount' => $this->decimal($order->amountMinor), 'item_name' => 'giving' === $order->purpose ? 'Church giving' : 'Church event ticket'];
            if ($order->recurring) {
                $now = $this->clock->now()->setTimezone(new DateTimeZone('Africa/Johannesburg'));
                $next = $now->modify('first day of next month');
                $day = min((int) $now->format('d'), (int) $next->format('t'));
                $fields += ['subscription_type' => '1', 'billing_date' => $next->setDate((int) $next->format('Y'), (int) $next->format('m'), $day)->format('Y-m-d'), 'recurring_amount' => $this->decimal($order->amountMinor), 'frequency' => '3', 'cycles' => '0'];
            }
            $fields = array_filter($fields, static fn ($value) => '' !== $value);
            $fields['signature'] = self::formSignature($fields, $this->config->passphrase);

            return ['id' => $order->id, 'url' => 'test' === $order->mode ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process', 'method' => 'POST', 'fields' => $fields];
        }
        if ($order->recurring) {
            throw new InvalidArgumentException('Yoco does not support recurring giving.');
        }
        try {
            $data = $this->http->request('POST', 'https://payments.yoco.com/api/checkouts', $this->options() + ['headers' => ['Authorization' => 'Bearer '.$this->config->yocoKey, 'Idempotency-Key' => $order->id], 'json' => ['amount' => $order->amountMinor, 'currency' => 'ZAR', 'successUrl' => $return, 'cancelUrl' => $return.'&returned=cancelled', 'failureUrl' => $return.'&returned=failed', 'metadata' => ['orderId' => $order->id], 'externalId' => $order->id]])->toArray();
            if (!is_string($data['id'] ?? null) || ($data['amount'] ?? null) !== $order->amountMinor || ($data['currency'] ?? null) !== 'ZAR' || ($data['processingMode'] ?? null) !== $order->mode || !$this->validYocoUrl($data['redirectUrl'] ?? null)) {
                throw new RuntimeException();
            }

            return ['id' => $data['id'], 'url' => $data['redirectUrl'], 'method' => 'GET', 'fields' => []];
        } catch (Throwable $e) {
            throw new RuntimeException('Yoco checkout could not be confirmed. Retry the same payment request; do not start another charge.', 0, $e);
        }
    }

    public function verify(string $provider, string $body, array $headers, string $clientIp): ?array
    {
        $mode = $this->mode($provider);
        if ('payfast' === $provider) {
            return $this->payfast($body, $clientIp, $mode);
        }
        $id = $headers['webhook-id'] ?? '';
        $timestamp = $headers['webhook-timestamp'] ?? '';
        if (!preg_match('/^[0-9]{1,12}$/D', $timestamp) || abs($this->clock->now()->getTimestamp() - (int) $timestamp) > 180 || !preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $id)) {
            throw new InvalidArgumentException('Invalid payment notification signature or timestamp.');
        }
        $secret = base64_decode(substr($this->config->yocoWebhookSecret, 6), true);
        if (false === $secret || strlen($secret) < 16) {
            throw new RuntimeException('Yoco webhook verification is not configured.');
        }
        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $secret, true));
        $valid = false;
        foreach (explode(' ', $headers['webhook-signature'] ?? '') as $signature) {
            if (str_starts_with($signature, 'v1,') && hash_equals($expected, substr($signature, 3))) {
                $valid = true;
            }
        }
        if (!$valid) {
            throw new InvalidArgumentException('Invalid payment notification signature.');
        }
        try {
            $event = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid payment notification.');
        }
        if (!is_array($event)) {
            throw new InvalidArgumentException('Invalid payment notification.');
        }
        $type = match ($event['type'] ?? '') {
            'payment.succeeded' => 'paid',
            'payment.failed' => 'failed',
            'refund.succeeded' => 'refunded',
            'refund.failed' => 'refund_failed',
            default => null,
        };
        if (null === $type) {
            return null;
        }
        $p = $event['payload'] ?? [];
        if (!is_array($p) || !is_int($p['amount'] ?? null) || $p['amount'] < 1 || !is_string($p['id'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{1,150}$/D', $p['id']) || !is_string($event['id'] ?? null) || !is_string($p['metadata']['checkoutId'] ?? null)) {
            throw new InvalidArgumentException('Incomplete payment notification.');
        }
        if (($p['mode'] ?? null) !== $mode) {
            throw new InvalidArgumentException('Payment notification mode does not match the configured provider.');
        }
        if (($p['status'] ?? null) !== ('paid' === $type || 'refunded' === $type ? 'succeeded' : 'failed')) {
            throw new InvalidArgumentException('Payment status does not match the notification.');
        }

        return ['provider' => 'yoco', 'mode' => $mode, 'eventId' => $event['id'], 'type' => $type, 'reference' => $p['id'], 'checkoutId' => $p['metadata']['checkoutId'], 'orderId' => $p['metadata']['orderId'] ?? null, 'amountMinor' => $p['amount'], 'currency' => $p['currency'] ?? null, 'token' => null, 'receivedOn' => $this->providerDate($p['createdDate'] ?? $event['createdDate'] ?? null)];
    }

    private function providerDate(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new InvalidArgumentException('Invalid provider transaction date.');
        }
        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidArgumentException('Invalid provider transaction date.');
        }
        $errors = DateTimeImmutable::getLastErrors();
        if (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new InvalidArgumentException('Invalid provider transaction date.');
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }

    private function payfast(string $body, string $ip, string $mode): ?array
    {
        $ranges = array_filter(array_map('trim', explode(',', $this->config->payfastIpRanges)));
        if (!IpUtils::checkIp($ip, $ranges)) {
            throw new InvalidArgumentException('Payment notification origin is not allowed.');
        }
        $data = [];
        foreach (explode('&', $body) as $pair) {
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $key) || array_key_exists($key, $data) || !isset($parts[1])) {
                throw new InvalidArgumentException('Invalid payment notification fields.');
            }
            $data[$key] = urldecode($parts[1]);
        }
        $signature = $data['signature'] ?? '';
        unset($data['signature']);
        // ITN signatures include empty fields and preserve the provider's field order.
        $encoded = http_build_query($data, '', '&', PHP_QUERY_RFC1738);
        $expected = md5($encoded.'&passphrase='.urlencode($this->config->passphrase));
        if (!hash_equals($expected, $signature) || ($data['merchant_id'] ?? null) !== $this->config->merchantId) {
            throw new InvalidArgumentException('Invalid payment notification signature or merchant.');
        }
        try {
            $valid = $this->http->request('POST', 'test' === $mode ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate', $this->options() + ['headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'body' => $encoded])->getContent();
        } catch (Throwable $e) {
            throw new RuntimeException('Payfast verification is temporarily unavailable.', 0, $e);
        }
        if ('VALID' !== trim($valid)) {
            throw new InvalidArgumentException('Payfast could not verify this notification.');
        }
        $type = match ($data['payment_status'] ?? '') {
            'COMPLETE' => 'paid',
            'FAILED', 'CANCELLED' => 'failed',
            default => null,
        };
        if (null === $type) {
            return null;
        }
        if (!preg_match('/^([0-9]{1,9})\.([0-9]{2})$/D', $data['amount_gross'] ?? '', $amount) || !preg_match('/^[A-Za-z0-9_-]{1,150}$/D', $data['pf_payment_id'] ?? '')) {
            throw new InvalidArgumentException('Invalid payment notification amount or reference.');
        }
        $token = $data['token'] ?? null;
        if (null !== $token && !preg_match('/^[a-fA-F0-9-]{36}$/D', $token)) {
            throw new InvalidArgumentException('Invalid subscription token.');
        }

        return ['provider' => 'payfast', 'mode' => $mode, 'eventId' => $data['pf_payment_id'].':'.$type, 'type' => $type, 'reference' => $data['pf_payment_id'], 'checkoutId' => null, 'orderId' => $data['m_payment_id'] ?? '' ?: null, 'amountMinor' => (int) $amount[1] * 100 + (int) $amount[2], 'currency' => 'ZAR', 'token' => $token];
    }

    public function cancelSubscription(PaymentOrder $order, string $token): void
    {
        if ('cancelled' === $this->subscriptionStatus($order, $token)) {
            return;
        }
        $result = $this->payfastApi($order, $token, 'cancel', 'PUT');
        if (($result['data']['response'] ?? null) !== true) {
            throw new RuntimeException('Subscription cancellation is unconfirmed. Check provider status before retrying.');
        }
    }

    public function subscriptionStatus(PaymentOrder $order, string $token): string
    {
        $data = $this->payfastApi($order, $token, 'fetch', 'GET');

        return match (strtoupper((string) ($data['data']['response']['status_text'] ?? ''))) {
            'CANCELLED' => 'cancelled',
            'ACTIVE' => 'active',
            'PAUSED' => 'paused',
            'LOCKED' => 'locked',
            default => throw new RuntimeException('Payfast subscription status was not recognised. Review its dashboard.'),
        };
    }

    private function payfastApi(PaymentOrder $order, string $token, string $action, string $method): array
    {
        if ('payfast' !== $order->provider || $this->mode('payfast') !== $order->mode || !preg_match('/^[a-fA-F0-9-]{36}$/D', $token)) {
            throw new DomainException('Subscription configuration does not match this payment.');
        }
        $headers = ['merchant-id' => $this->config->merchantId, 'version' => 'v1', 'timestamp' => $this->clock->now()->format(DATE_ATOM)];
        $sign = $headers + ['passphrase' => $this->config->passphrase];
        ksort($sign);
        $headers['signature'] = md5(http_build_query($sign, '', '&', PHP_QUERY_RFC1738));
        try {
            $data = $this->http->request($method, 'https://api.payfast.co.za/subscriptions/'.$token.'/'.$action.('test' === $order->mode ? '?testing=true' : ''), $this->options() + ['headers' => $headers])->toArray();
            if (($data['status'] ?? null) !== 'success' || ($data['code'] ?? null) !== 200) {
                throw new RuntimeException();
            }

            return $data;
        } catch (Throwable $e) {
            throw new RuntimeException('Payfast subscription status could not be confirmed. Review it in the provider dashboard.', 0, $e);
        }
    }

    public static function formSignature(array $fields, string $passphrase): string
    {
        $sign = [];
        foreach ($fields as $key => $value) {
            if ('' !== (string) $value) {
                $sign[$key] = trim((string) $value);
            }
        }

        return md5(http_build_query($sign, '', '&', PHP_QUERY_RFC1738).'&passphrase='.urlencode(trim($passphrase)));
    }

    private function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function options(): array
    {
        return ['timeout' => 7, 'max_duration' => 10, 'max_redirects' => 0];
    }

    private function validYocoUrl(mixed $url): bool
    {
        return is_string($url) && 'https' === parse_url($url, PHP_URL_SCHEME) && 'c.yoco.com' === parse_url($url, PHP_URL_HOST) && !parse_url($url, PHP_URL_USER);
    }
}
