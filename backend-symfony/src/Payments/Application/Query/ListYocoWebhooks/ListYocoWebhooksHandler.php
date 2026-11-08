<?php

declare(strict_types=1);

namespace App\Payments\Application\Query\ListYocoWebhooks;

use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\Contract\ProviderConfigurationReader;
use App\Payments\Application\Contract\WebhookOperationReader;
use App\Payments\Application\Contract\YocoWebhook;
use App\Payments\Application\Contract\YocoWebhookReader;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderSettings;

/** Lists the provider's webhook subscriptions (a read-only provider call, outside any transaction) and pending operations. */
final readonly class ListYocoWebhooksHandler
{
    public function __construct(
        private PaymentAccess $access,
        private ProviderConfigurationReader $configurations,
        private PaymentCredentialVault $vault,
        private WebhookOperationReader $operations,
        private YocoWebhookReader $webhooks,
    ) {
    }

    public function handle(ListYocoWebhooksQuery $q): WebhookListing
    {
        $this->access->configure($q->actorId);
        ProviderSettings::validate('yoco', $q->mode);
        $configuration = $this->configurations->latest('yoco', $q->mode)
            ?? throw PaymentException::conflict('Save the Yoco API key and backend URL first.');
        $key = $this->vault->decrypt($configuration->encryptedCredentials)->yocoKey;
        if (!str_starts_with($key, 'sk_'.$q->mode.'_')) {
            throw PaymentException::conflict('Save a Yoco secret API key for this environment first.');
        }

        return new WebhookListing(
            array_values(array_filter($this->webhooks->list($key), static fn (YocoWebhook $v): bool => $v->mode === $q->mode)),
            $this->operations->pending($q->mode),
        );
    }
}
