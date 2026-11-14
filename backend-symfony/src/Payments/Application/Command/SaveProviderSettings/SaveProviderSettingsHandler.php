<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\SaveProviderSettings;

use App\Payments\Application\Contract\LegacyPaymentConfiguration;
use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\PaymentAccess;
use App\Payments\Application\PaymentHash;
use App\Payments\Domain\HttpsOrigin;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;
use App\Payments\Domain\ProviderSettings;
use App\Payments\Domain\Repository\ProviderConfigurationRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use DateInterval;
use Psr\Clock\ClockInterface;

/**
 * Saves a provider's settings head and, when the connection details changed, a new immutable encrypted revision.
 * Changed details must be saved disabled and pass a connection check (within 24 hours) before new checkouts are
 * enabled. Blank credentials keep the saved ones. Lock: the provider settings head.
 */
final readonly class SaveProviderSettingsHandler implements CommandHandler
{
    public function __construct(
        private PaymentAccess $access,
        private ProviderConfigurationRepository $repository,
        private PaymentCredentialVault $vault,
        private LegacyPaymentConfiguration $legacy,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    public function handle(SaveProviderSettingsCommand $c): null
    {
        $this->access->configure($c->actorId);
        ProviderSettings::validate($c->provider, $c->mode);
        $reason = PaymentOrder::text($c->reason, 1000, 'Change reason');
        $current = $this->repository->lock($c->provider);
        $this->access->configure($c->actorId);
        $current->expected($c->expectedVersion);
        $updated = new ProviderSettings($c->provider, $c->mode, $c->enabled, $c->monthly, true, $current->version + 1);
        $prior = $this->repository->latest($c->provider, $c->mode);
        $origin = HttpsOrigin::public($c->publicUrl);
        $backend = null === $c->backendUrl ? ($prior->backendUrl ?? $origin) : HttpsOrigin::public($c->backendUrl);
        $before = null === $prior ? $this->legacy->credentials($c->provider, $c->mode) : $this->vault->decrypt($prior->encryptedCredentials);
        $credentials = $before->replace($c->replacements ?? ProviderCredentials::none());
        foreach ([...$before->protectedValues(), ...$credentials->protectedValues()] as $secret) {
            if (str_contains($reason, $secret)) {
                throw PaymentException::invalid('Keep credentials out of the change reason.');
            }
        }
        $hash = PaymentHash::of('', '', '', $credentials->yocoKey, $credentials->webhookSecret);
        $now = $this->clock->now();
        $revision = $prior;
        if (null === $prior || $prior->publicUrl !== $origin || $prior->backendUrl !== $backend || $prior->credentialHash !== $hash) {
            $revision = new ProviderConfiguration(
                $this->ids->uuid(),
                $c->provider,
                $c->mode,
                null === $prior ? 1 : $prior->sequence + 1,
                $origin,
                '',
                $this->vault->encrypt($credentials),
                $hash,
                false,
                false,
                false,
                '' !== $credentials->yocoKey,
                '' !== $credentials->webhookSecret,
                $c->actorId,
                $now,
                $backend
            );
            if ($c->enabled) {
                throw PaymentException::conflict('Save changed connection details with new checkouts disabled, test the saved connection, then enable the provider.');
            }
            $this->repository->insert($revision);
        }
        if ($c->enabled) {
            $this->activation($c, $revision, $credentials);
        }
        $this->repository->save($updated);
        $this->repository->audit(
            $c->provider,
            $c->mode,
            $updated->version,
            'SETTINGS_SAVED',
            $reason,
            implode(', ', self::changedFields($before, $credentials, $prior, $origin, $backend, $current, $c)),
            $c->actorId,
            $now
        );

        return null;
    }

    private function activation(SaveProviderSettingsCommand $c, ?ProviderConfiguration $revision, ProviderCredentials $credentials): void
    {
        if ('yoco' === $c->provider && $this->repository->activeWebhookRemoval($c->mode)) {
            throw PaymentException::conflict('Resolve the pending webhook removal before activation.');
        }
        if (null === $revision || !$this->vault->ready() || !$credentials->complete($c->mode)) {
            throw PaymentException::conflict('Complete the provider credentials and connection details before activation.');
        }
        $check = $this->repository->latestCheck($revision->id);
        if (null === $check || !$check->successful || $check->checkedAt <= $this->clock->now()->sub(new DateInterval('PT24H'))) {
            throw PaymentException::conflict('Run a successful connection check on these saved settings before activation.');
        }
    }

    /** @return list<string> */
    private static function changedFields(
        ProviderCredentials $before,
        ProviderCredentials $after,
        ?ProviderConfiguration $prior,
        string $origin,
        string $backend,
        ProviderSettings $current,
        SaveProviderSettingsCommand $c,
    ): array {
        $fields = [];
        $checks = [
            'API key' => $before->yocoKey !== $after->yocoKey,
            'webhook secret' => $before->webhookSecret !== $after->webhookSecret,
            'frontend URL' => null === $prior || $prior->publicUrl !== $origin,
            'backend URL' => null === $prior || $prior->backendUrl !== $backend,
            'new checkouts' => $current->enabled !== $c->enabled,
            'environment' => $current->mode !== $c->mode,
        ];
        foreach ($checks as $label => $changed) {
            if ($changed) {
                $fields[] = $label;
            }
        }

        return $fields;
    }
}
