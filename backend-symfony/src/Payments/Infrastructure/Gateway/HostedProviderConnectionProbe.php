<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\ProbeResult;
use App\Payments\Application\Contract\ProviderConnectionProbe;
use App\Payments\Application\YocoWebhookManagement;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;

/** Yoco's webhook list: it neither creates, changes nor charges anything. */
final readonly class HostedProviderConnectionProbe implements ProviderConnectionProbe
{
    public function __construct(private ProviderHttp $http)
    {
    }

    public function check(ProviderConfiguration $configuration, ProviderCredentials $credentials): ProbeResult
    {
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
