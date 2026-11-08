<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\HostedCheckout;
use App\Payments\Application\Contract\PaymentGateway;
use App\Payments\Application\Contract\PaymentProvider;
use App\Payments\Application\Contract\ProviderNotification;
use App\Payments\Application\PaymentReceiptPreview;
use App\Payments\Domain\JavaBase64;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Payfast and Yoco hosted checkout (Java `HostedPaymentGateway`). Each call uses the connection values of the order's
 * own configuration revision. Notifications are authenticated from the unchanged raw body before anything is parsed
 * for meaning: Yoco by HMAC-SHA256 over id.timestamp.body with a three-minute window, Payfast by trusted source
 * network, MD5 signature with the passphrase, merchant id and Payfast's server-side validate round trip.
 */
final readonly class HostedPaymentGateway implements PaymentGateway
{
    private const string UUID = '/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/';

    public function __construct(
        private ProviderSettingsSource $source,
        private ProviderHttp $http,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
    ) {
    }

    public function providers(): array
    {
        return array_map(function (string $id): PaymentProvider {
            $resolved = $this->source->current($id);

            return new PaymentProvider(
                $id,
                'payfast' === $id ? 'Payfast' : 'Yoco',
                $resolved->available,
                $resolved->settings->mode($id),
                $resolved->monthly
            );
        }, PaymentOrder::PROVIDERS);
    }

    public function mode(string $provider): string
    {
        $resolved = $this->source->current($provider);
        if (!$resolved->available) {
            throw PaymentException::unavailable('This payment provider is disabled or not configured. Ask the church administrator.');
        }

        return $resolved->settings->mode($provider);
    }

    public function validateOrder(PaymentOrder $order): void
    {
        if (!$this->source->forOrder($order)->available($order->provider)) {
            throw PaymentException::unavailable('The original payment connection is unavailable.');
        }
    }

    public function checkout(PaymentOrder $order, string $donorName, string $email): HostedCheckout
    {
        $settings = $this->source->forOrder($order);
        if ($this->boundMode($settings, $order->provider) !== $order->mode) {
            throw PaymentException::conflict('Provider environment changed; review this checkout before creating another.');
        }
        $returned = $settings->base().($order->selfService ? '/my-giving?order=' : '/payments?order=').$order->id;

        return 'payfast' === $order->provider
            ? $this->payfastCheckout($settings, $order, $returned, $donorName, $email)
            : $this->yocoCheckout($settings, $order, $returned);
    }

    public function verify(string $provider, string $rawBody, array $headers, string $ip): ?ProviderNotification
    {
        if (\strlen($rawBody) > 65536) {
            throw PaymentException::invalid('Payment notification is too large.');
        }
        $settings = $this->source->forNotification($provider, $rawBody);
        $mode = $this->boundMode($settings, $provider);
        try {
            return 'payfast' === $provider ? $this->payfast($settings, $rawBody, $ip, $mode) : $this->yoco($settings, $rawBody, $headers, $mode);
        } catch (PaymentException $error) {
            throw $error;
        } catch (Exception) {
            throw PaymentException::invalid('Invalid payment notification.');
        }
    }

    public function subscription(PaymentOrder $order, #[SensitiveParameter] string $token, bool $cancel): string
    {
        $settings = $this->source->forOrder($order);
        if ('payfast' !== $order->provider || $this->boundMode($settings, 'payfast') !== $order->mode || 1 !== preg_match('/\A[a-fA-F0-9-]{36}\z/', $token)) {
            throw PaymentException::conflict('Subscription configuration does not match the original payment.');
        }
        if ($cancel) {
            $result = $this->api($settings, $order, $token, 'cancel', 'PUT');
            if (true !== ProviderJson::path($result, 'data', 'response')) {
                throw PaymentException::unavailable('Subscription cancellation is unconfirmed; check the provider before retrying.');
            }

            return 'CANCELLED';
        }
        $status = strtoupper(ProviderJson::text(ProviderJson::path($this->api($settings, $order, $token, 'fetch', 'GET'), 'data', 'response', 'status_text')));
        if (!\in_array($status, ['ACTIVE', 'PAUSED', 'LOCKED', 'CANCELLED'], true)) {
            throw PaymentException::unavailable('Provider subscription status is not recognised.');
        }

        return $status;
    }

    public static function validYocoUrl(string $value): bool
    {
        if (1 !== preg_match('#\A[!-~]+\z#', $value) || str_contains($value, '\\')) {
            return false;
        }
        $parts = parse_url($value);

        return \is_array($parts) && 'https' === ($parts['scheme'] ?? null) && 'c.yoco.com' === ($parts['host'] ?? null)
            && !isset($parts['user']) && !isset($parts['pass']) && \in_array($parts['port'] ?? 443, [443], true);
    }

    /** The environment of the bound settings; 503 when they cannot reach the provider. */
    private function boundMode(GatewaySettings $settings, string $provider): string
    {
        if (!$settings->available($provider)) {
            throw PaymentException::unavailable('This payment provider is not configured. Ask the church administrator.');
        }

        return $settings->mode($provider);
    }

    private function payfastCheckout(GatewaySettings $settings, PaymentOrder $order, string $returned, string $name, string $email): HostedCheckout
    {
        $fields = [
            'merchant_id' => $settings->merchantId,
            'merchant_key' => $settings->merchantKey,
            'return_url' => $returned,
            'cancel_url' => $returned.'&returned=cancelled',
            'notify_url' => $settings->backendBase().'/api/payments/webhooks/payfast',
            'name_first' => mb_substr($name, 0, 100),
        ];
        if ('' !== trim($email)) {
            $fields['email_address'] = $email;
        }
        $fields['m_payment_id'] = $order->id;
        $fields['amount'] = PaymentReceiptPreview::decimal($order->amountMinor);
        $fields['item_name'] = 'Church giving';
        if ($order->recurring) {
            $fields['subscription_type'] = '1';
            $fields['billing_date'] = $this->calendar->today()->plusMonths(1)->iso();
            $fields['recurring_amount'] = PaymentReceiptPreview::decimal($order->amountMinor);
            $fields['frequency'] = '3';
            $fields['cycles'] = '0';
        }
        $fields['signature'] = ProviderForms::signature($fields, $settings->passphrase);

        return new HostedCheckout($order->id, 'test' === $order->mode ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process', $fields);
    }

    private function yocoCheckout(GatewaySettings $settings, PaymentOrder $order, string $returned): HostedCheckout
    {
        $body = json_encode([
            'amount' => $order->amountMinor,
            'currency' => 'ZAR',
            'successUrl' => $returned,
            'cancelUrl' => $returned.'&returned=cancelled',
            'failureUrl' => $returned.'&returned=failed',
            'metadata' => ['orderId' => $order->id],
            'externalId' => $order->id,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $failure = 'Yoco checkout could not be confirmed. Retry this same payment request.';
        $response = ProviderJson::parse($this->http->send('POST', 'https://payments.yoco.com/api/checkouts', [
            'Authorization' => 'Bearer '.$settings->yocoKey,
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $order->id,
        ], $body), $failure);
        $amount = ProviderJson::path($response, 'amount');
        $redirect = ProviderJson::text(ProviderJson::path($response, 'redirectUrl'));
        if (!(\is_int($amount) || \is_float($amount)) || (int) $amount !== $order->amountMinor
            || 'ZAR' !== ProviderJson::text(ProviderJson::path($response, 'currency'))
            || $order->mode !== ProviderJson::text(ProviderJson::path($response, 'processingMode')) || !self::validYocoUrl($redirect)) {
            throw PaymentException::unavailable($failure);
        }

        return new HostedCheckout(self::reference(ProviderJson::text(ProviderJson::path($response, 'id'))), $redirect, []);
    }

    /**
     * @param array<string, string> $headers
     *
     * @throws JsonException
     */
    private function yoco(GatewaySettings $settings, string $body, array $headers, string $mode): ?ProviderNotification
    {
        $id = $headers['webhook-id'] ?? '';
        $timestamp = $headers['webhook-timestamp'] ?? '';
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{1,200}\z/', $id) || 1 !== preg_match('/\A[0-9]{1,12}\z/', $timestamp)
            || abs($this->clock->now()->getTimestamp() - (int) $timestamp) > 180) {
            throw PaymentException::invalid('Invalid payment notification timestamp.');
        }
        if (\strlen($settings->yocoWebhookSecret) < 6) {
            throw PaymentException::invalid('Invalid payment notification.');
        }
        $secret = JavaBase64::decode(substr($settings->yocoWebhookSecret, 6))
            ?? throw PaymentException::invalid('Invalid payment notification.');
        if (\strlen($secret) < 16) {
            throw PaymentException::unavailable('Webhook verification is not configured.');
        }
        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $secret, true));
        $valid = false;
        foreach (explode(' ', $headers['webhook-signature'] ?? '') as $signature) {
            if (str_starts_with($signature, 'v1,') && hash_equals($expected, substr($signature, 3))) {
                $valid = true;
            }
        }
        if (!$valid) {
            throw PaymentException::invalid('Invalid payment notification signature.');
        }
        $event = json_decode($body, true, 32, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        $type = match (ProviderJson::text(ProviderJson::path($event, 'type'))) {
            'payment.succeeded' => 'PAID',
            'payment.failed' => 'FAILED',
            'refund.succeeded' => 'REFUNDED',
            'refund.failed' => 'REFUND_FAILED',
            default => null,
        };
        if (null === $type) {
            return null;
        }
        $payload = ProviderJson::path($event, 'payload');
        $amount = ProviderJson::path($payload, 'amount');
        $success = 'PAID' === $type || 'REFUNDED' === $type;
        if (!\is_int($amount) || $amount < 1 || $amount > 100_000_000 || $mode !== ProviderJson::text(ProviderJson::path($payload, 'mode'))
            || ($success ? 'succeeded' : 'failed') !== ProviderJson::text(ProviderJson::path($payload, 'status'))) {
            throw PaymentException::invalid('Payment notification has invalid amount, environment or status.');
        }
        $zone = new DateTimeZone($this->calendar->timeZone());
        $today = $this->calendar->today();
        $date = ProviderJson::text(ProviderJson::path($payload, 'createdDate'), ProviderJson::text(ProviderJson::path($event, 'createdDate')));
        $received = '' === trim($date) ? $today : CalendarDate::fromInstant(self::offsetDateTime($date), $zone);
        if ($received->isAfter($today)) {
            throw PaymentException::invalid('Provider transaction date is in the future.');
        }

        return new ProviderNotification(
            'yoco',
            $mode,
            self::reference(ProviderJson::text(ProviderJson::path($event, 'id'))),
            $type,
            self::reference(ProviderJson::text(ProviderJson::path($payload, 'id'))),
            self::reference(ProviderJson::text(ProviderJson::path($payload, 'metadata', 'checkoutId'))),
            self::uuid(ProviderJson::text(ProviderJson::path($payload, 'metadata', 'orderId'))),
            $amount,
            ProviderJson::text(ProviderJson::path($payload, 'currency')),
            null,
            $received
        );
    }

    private function payfast(GatewaySettings $settings, string $body, string $ip, string $mode): ?ProviderNotification
    {
        $trusted = false;
        foreach (explode(',', $settings->ipRanges) as $range) {
            $range = trim($range);
            if ('' !== $range && (IpRange::parse($range)?->matches($ip) ?? throw PaymentException::invalid('Invalid payment notification.'))) {
                $trusted = true;
            }
        }
        if (!$trusted) {
            throw PaymentException::invalid('Payment notification origin is not allowed.');
        }
        $data = [];
        foreach (explode('&', $body) as $pair) {
            $part = explode('=', $pair, 2);
            $key = urldecode($part[0]);
            if (2 !== \count($part) || 1 !== preg_match('/\A[A-Za-z0-9_]+\z/', $key) || \array_key_exists($key, $data)) {
                throw PaymentException::invalid('Invalid payment notification fields.');
            }
            $data[$key] = urldecode($part[1]);
        }
        $signature = $data['signature'] ?? null;
        unset($data['signature']);
        $encoded = ProviderForms::form($data);
        if (null === $signature || !hash_equals(md5($encoded.'&passphrase='.urlencode($settings->passphrase)), $signature)
            || $settings->merchantId !== ($data['merchant_id'] ?? null)) {
            throw PaymentException::invalid('Invalid payment signature or merchant.');
        }
        $valid = $this->http->send(
            'POST',
            'test' === $mode ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $encoded
        );
        if ('VALID' !== trim($valid)) {
            throw PaymentException::invalid('Payfast could not verify the notification.');
        }
        $type = match ($data['payment_status'] ?? '') {
            'COMPLETE' => 'PAID',
            'FAILED', 'CANCELLED' => 'FAILED',
            default => null,
        };
        if (null === $type) {
            return null;
        }
        $amount = $data['amount_gross'] ?? '';
        if (1 !== preg_match('/\A([0-9]{1,9})\.([0-9]{2})\z/', $amount, $digits)) {
            throw PaymentException::invalid('Invalid payment amount.');
        }
        $minor = (int) $digits[1] * 100 + (int) $digits[2];
        if ($minor < 1 || $minor > 100_000_000) {
            throw PaymentException::invalid('Invalid payment amount.');
        }
        $token = $data['token'] ?? null;
        if (null !== $token && 1 !== preg_match('/\A[a-fA-F0-9-]{36}\z/', $token)) {
            throw PaymentException::invalid('Invalid subscription token.');
        }
        $reference = self::reference($data['pf_payment_id'] ?? null);

        return new ProviderNotification(
            'payfast',
            $mode,
            $reference.':'.$type,
            $type,
            $reference,
            null,
            self::uuid($data['m_payment_id'] ?? null),
            $minor,
            'ZAR',
            $token,
            null
        );
    }

    private function api(GatewaySettings $settings, PaymentOrder $order, #[SensitiveParameter] string $token, string $action, string $method): mixed
    {
        $headers = ProviderForms::apiHeaders($settings->merchantId, $settings->passphrase, JavaTimestamp::utc($this->clock->now()));
        $failure = 'Provider subscription request is unconfirmed.';
        $result = ProviderJson::parse($this->http->send($method, 'https://api.payfast.co.za/subscriptions/'.$token.'/'.$action
            .('test' === $order->mode ? '?testing=true' : ''), $headers, ''), $failure);
        $code = ProviderJson::path($result, 'code');
        if ('success' !== ProviderJson::text(ProviderJson::path($result, 'status')) || !(200 === $code || '200' === $code)) {
            throw PaymentException::unavailable($failure);
        }

        return $result;
    }

    /** `OffsetDateTime.parse` (ISO_OFFSET_DATE_TIME). */
    private static function offsetDateTime(string $value): DateTimeImmutable
    {
        if (1 !== preg_match('/\A(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})(?::(\d{2})(?:\.(\d{1,9}))?)?(Z|[+-]\d{2}:\d{2})\z/', $value, $p)) {
            throw PaymentException::invalid('Invalid payment notification.');
        }
        $text = $p[1].':'.('' === $p[2] ? '00' : $p[2]).'.'.substr(str_pad($p[3], 6, '0'), 0, 6).('Z' === $p[4] ? '+00:00' : $p[4]);
        $time = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $text);
        if (false === $time) {
            throw PaymentException::invalid('Invalid payment notification.');
        }

        return $time;
    }

    private static function uuid(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }
        if (1 !== preg_match(self::UUID, $value)) {
            throw PaymentException::invalid('Invalid payment notification.');
        }

        return strtolower($value);
    }

    private static function reference(?string $value): string
    {
        if (null === $value || 1 !== preg_match('/\A[A-Za-z0-9_-]{1,150}\z/', $value)) {
            throw PaymentException::invalid('Invalid provider reference.');
        }

        return $value;
    }
}
