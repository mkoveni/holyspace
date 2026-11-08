<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\ProbeResult;
use App\Payments\Application\Contract\ProviderConnectionProbe;
use App\Payments\Application\YocoWebhookManagement;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;
use Psr\Clock\ClockInterface;

/** Payfast's authenticated `ping` and Yoco's webhook list: neither creates, changes nor charges anything. */
final readonly class HostedProviderConnectionProbe implements ProviderConnectionProbe
{
    public function __construct(private ProviderHttp $http, private ClockInterface $clock)
    {
    }

    public function validateNetworks(string $ipRanges): void
    {
        $ranges = explode(',', $ipRanges);
        if (\count($ranges) > 30) {
            throw PaymentException::invalid('Use at most 30 trusted notification networks.');
        }
        foreach ($ranges as $range) {
            $clean = trim($range);
            if ('' === $clean || 1 !== preg_match('/\A[0-9a-fA-F:.]+(?:\/[0-9]{1,3})?\z/', $clean) || str_ends_with($clean, '/0')) {
                throw PaymentException::invalid('Use explicit trusted IP addresses or restricted CIDR networks.');
            }
            if (str_contains($clean, '/') && 0 === (int) substr($clean, strpos($clean, '/') + 1)) {
                throw PaymentException::invalid('A universal notification network is not allowed.');
            }
            if (null === IpRange::parse($clean)) {
                throw PaymentException::invalid('A trusted notification network is invalid.');
            }
        }
    }

    public function check(ProviderConfiguration $configuration, ProviderCredentials $credentials): ProbeResult
    {
        if ('payfast' === $configuration->provider) {
            $headers = ProviderForms::apiHeaders($credentials->merchantId, $credentials->passphrase, JavaTimestamp::utc($this->clock->now()));
            $response = str_replace('"', '', trim($this->http->send(
                'GET',
                'https://api.payfast.co.za/ping'.('test' === $configuration->mode ? '?testing=true' : ''),
                $headers,
                ''
            )));
            $confirmed = 'Payfast API' === $response || 'API V1' === $response;

            return new ProbeResult($confirmed, $confirmed
                ? 'API authentication confirmed. Complete a hosted checkout and callback pilot before taking live payments.'
                : 'Payfast did not confirm API authentication.');
        }
        $response = ProviderJson::parse($this->http->send(
            'GET',
            'https://payments.yoco.com/api/webhooks',
            ['Authorization' => 'Bearer '.$credentials->yocoKey],
            ''
        ), 'Provider connection could not be confirmed.');
        $found = false;
        $subscriptions = ProviderJson::path($response, 'subscriptions');
        foreach (\is_array($subscriptions) ? $subscriptions : [] as $subscription) {
            if (ProviderJson::text(ProviderJson::path($subscription, 'url')) === $configuration->backendUrl.YocoWebhookManagement::CALLBACK
                && ProviderJson::text(ProviderJson::path($subscription, 'mode')) === $configuration->mode) {
                $found = true;
            }
        }

        return new ProbeResult($found, $found
            ? 'API access and callback registration confirmed. A callback pilot is still required to verify the webhook secret.'
            : 'API access confirmed, but no matching callback URL was registered for this environment.');
    }
}
