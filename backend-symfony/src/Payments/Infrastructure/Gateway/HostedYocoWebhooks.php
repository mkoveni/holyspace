<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\RegisteredWebhook;
use App\Payments\Application\Contract\YocoWebhook;
use App\Payments\Application\Contract\YocoWebhookReader;
use App\Payments\Application\Contract\YocoWebhookWriter;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderCredentials;
use SensitiveParameter;

final readonly class HostedYocoWebhooks implements YocoWebhookReader, YocoWebhookWriter
{
    private const string URL = 'https://payments.yoco.com/api/webhooks';

    public function __construct(private ProviderHttp $http)
    {
    }

    public function list(#[SensitiveParameter] string $secretKey): array
    {
        $failure = 'Yoco webhook list could not be confirmed.';
        $items = ProviderJson::path(ProviderJson::parse($this->http->send('GET', self::URL, $this->headers($secretKey), ''), $failure), 'subscriptions');
        if (!\is_array($items) || !array_is_list($items) || \count($items) > 100) {
            throw PaymentException::unavailable($failure);
        }

        return array_map(static fn (mixed $item): YocoWebhook => new YocoWebhook(
            ProviderJson::text(ProviderJson::path($item, 'id')),
            ProviderJson::text(ProviderJson::path($item, 'name')),
            ProviderJson::text(ProviderJson::path($item, 'url')),
            ProviderJson::text(ProviderJson::path($item, 'mode')),
        ), $items);
    }

    public function register(#[SensitiveParameter] string $secretKey, string $name, string $callback, string $mode): RegisteredWebhook
    {
        $failure = 'Yoco registration could not be confirmed. Inspect the webhook list before registering again.';
        $result = ProviderJson::parse($this->http->send(
            'POST',
            self::URL,
            $this->headers($secretKey),
            json_encode(['name' => $name, 'url' => $callback], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)
        ), $failure);
        $id = ProviderJson::text(ProviderJson::path($result, 'id'));
        $secret = ProviderJson::text(ProviderJson::path($result, 'secret'));
        if (1 !== preg_match('/\Asub_[A-Za-z0-9_-]{1,150}\z/', $id) || ProviderJson::text(ProviderJson::path($result, 'url')) !== $callback
            || ProviderJson::text(ProviderJson::path($result, 'mode')) !== $mode
            || !new ProviderCredentials(null, null, null, $secretKey, $secret)->complete('yoco', $mode)) {
            throw PaymentException::unavailable($failure);
        }

        return new RegisteredWebhook($id, $secret);
    }

    public function remove(#[SensitiveParameter] string $secretKey, string $id): void
    {
        if (1 !== preg_match('/\Asub_[A-Za-z0-9_-]{1,150}\z/', $id)) {
            throw PaymentException::invalid('Choose a valid Yoco subscription.');
        }
        $this->http->send('DELETE', self::URL.'/'.$id, $this->headers($secretKey), '');
    }

    /** @return array<string, string> */
    private function headers(#[SensitiveParameter] string $key): array
    {
        return ['Authorization' => 'Bearer '.$key, 'Content-Type' => 'application/json'];
    }
}
