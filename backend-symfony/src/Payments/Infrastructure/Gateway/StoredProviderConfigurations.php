<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Gateway;

use App\Payments\Application\Contract\LegacyPaymentConfiguration;
use App\Payments\Application\Contract\PaymentConfigurationBinding;
use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\Contract\ProviderConfigurationReader;
use App\Payments\Application\PaymentHash;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;
use App\Payments\Domain\Repository\PaymentRepository;
use App\Payments\Domain\Repository\ProviderConfigurationRepository;
use Exception;
use JsonException;

/**
 * Resolves connection values (Java `StoredProviderConfigurations`): the deployment bootstrap until a provider is
 * managed, then the head's latest revision for new checkouts and each order's own pinned revision afterwards.
 */
final readonly class StoredProviderConfigurations implements ProviderSettingsSource, PaymentConfigurationBinding, LegacyPaymentConfiguration
{
    public function __construct(
        private ProviderConfigurationReader $reader,
        private ProviderConfigurationRepository $repository,
        private PaymentCredentialVault $vault,
        private GatewaySettings $bootstrap,
        private PaymentRepository $payments,
    ) {
    }

    public function current(string $provider): ResolvedSettings
    {
        $head = $this->reader->head($provider) ?? throw PaymentException::invalid('Choose a supported provider.');
        if (!$head->managed) {
            return new ResolvedSettings($this->bootstrap, $this->bootstrap->available($provider), 'payfast' === $provider);
        }
        $latest = $this->reader->latest($provider, $head->mode);
        if (null === $latest || !$this->vault->ready()) {
            return new ResolvedSettings(new GatewaySettings('', $head->mode, '', '', '', '', $head->mode, '', '', $this->bootstrap->vaultKey, ''), false, $head->monthly);
        }
        $settings = $this->materialize($latest);

        return new ResolvedSettings($settings, $head->enabled && $settings->available($provider)
            && ('yoco' !== $provider || !$this->repository->activeWebhookRemoval($head->mode)), $head->monthly);
    }

    public function bind(string $provider, string $mode, bool $recurring): ?string
    {
        $head = $this->repository->lock($provider);
        if (!$head->managed) {
            return null;
        }
        if ('yoco' === $provider && $this->repository->activeWebhookRemoval($mode)) {
            throw PaymentException::conflict('A webhook removal needs review before new checkouts can start.');
        }
        if (!$head->enabled || $head->mode !== $mode || ($recurring && !$head->monthly)) {
            throw PaymentException::conflict('Payment settings changed or this checkout option is disabled. Refresh the provider list.');
        }

        return ($this->repository->latest($provider, $mode) ?? throw PaymentException::conflict('Configure this payment environment first.'))->id;
    }

    public function forOrder(PaymentOrder $order): GatewaySettings
    {
        if (null === $order->configurationId) {
            if ($this->bootstrap->mode($order->provider) !== $order->mode || !$this->bootstrap->available($order->provider)) {
                throw PaymentException::unavailable('The deployment credentials for this older payment are unavailable. Restore its original provider credentials.');
            }

            return $this->bootstrap;
        }
        $revision = $this->reader->revision($order->configurationId);
        if (null === $revision || $revision->provider !== $order->provider || $revision->mode !== $order->mode) {
            throw PaymentException::unavailable('The original payment configuration is unavailable.');
        }

        return $this->materialize($revision);
    }

    public function forNotification(string $provider, string $body): GatewaySettings
    {
        try {
            $order = 'yoco' === $provider ? $this->yocoOrder($body) : ('payfast' === $provider ? $this->payfastOrder($body) : null);
        } catch (PaymentException $error) {
            throw $error;
        } catch (Exception) {
            throw PaymentException::invalid('Invalid payment notification.');
        }

        return null === $order ? $this->current($provider)->settings : $this->forOrder($order);
    }

    public function credentials(string $provider, string $mode): ProviderCredentials
    {
        if ('payfast' === $provider && $this->bootstrap->payfastMode === $mode) {
            return new ProviderCredentials($this->bootstrap->merchantId, $this->bootstrap->merchantKey, $this->bootstrap->passphrase);
        }
        if ('yoco' === $provider && $this->bootstrap->yocoMode === $mode) {
            return new ProviderCredentials(null, null, null, $this->bootstrap->yocoKey, $this->bootstrap->yocoWebhookSecret);
        }

        return ProviderCredentials::none();
    }

    /** @throws JsonException */
    private function yocoOrder(string $body): ?PaymentOrder
    {
        $payload = ProviderJson::path(json_decode($body, true, 32, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING), 'payload');
        $checkout = ProviderJson::text(ProviderJson::path($payload, 'metadata', 'checkoutId'));
        $id = $this->payments->lookup('yoco', ProviderJson::text(ProviderJson::path($payload, 'mode')), $checkout, null)
            ?? self::uuid(ProviderJson::text(ProviderJson::path($payload, 'metadata', 'orderId')));

        return $this->order('yoco', $id);
    }

    private function payfastOrder(string $body): ?PaymentOrder
    {
        $fields = [];
        foreach (explode('&', $body) as $pair) {
            $parts = explode('=', $pair, 2);
            if (2 === \count($parts)) {
                $fields[urldecode($parts[0])] = urldecode($parts[1]);
            }
        }
        $order = $this->order('payfast', self::uuid($fields['m_payment_id'] ?? null));
        if (null === $order && isset($fields['token'])) {
            $hash = PaymentHash::of($fields['token']);
            $order = $this->order('payfast', $this->payments->lookup('payfast', 'live', null, $hash) ?? $this->payments->lookup('payfast', 'test', null, $hash));
        }

        return $order;
    }

    private function order(string $provider, ?string $id): ?PaymentOrder
    {
        $order = null === $id ? null : $this->payments->find($id);

        return null !== $order && $order->provider === $provider ? $order : null;
    }

    private function materialize(ProviderConfiguration $revision): GatewaySettings
    {
        $c = $this->vault->decrypt($revision->encryptedCredentials);

        return new GatewaySettings(
            $revision->publicUrl,
            $revision->mode,
            $c->merchantId,
            $c->merchantKey,
            $c->passphrase,
            $revision->ipRanges,
            $revision->mode,
            $c->yocoKey,
            $c->webhookSecret,
            $this->bootstrap->vaultKey,
            $revision->backendUrl
        );
    }

    private static function uuid(?string $value): ?string
    {
        $value = strtolower(trim($value ?? ''));

        return 1 === preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $value) ? $value : null;
    }
}
