<?php

declare(strict_types=1);

namespace App\Payments\Application;

use App\Payments\Application\Command\SaveProviderSettings\SaveProviderSettingsCommand;
use App\Payments\Application\Command\SaveProviderSettings\SaveProviderSettingsHandler;
use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\Contract\YocoWebhook;
use App\Payments\Application\Contract\YocoWebhookReader;
use App\Payments\Application\Contract\YocoWebhookWriter;
use App\Payments\Domain\ConfigurationCheck;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\PaymentOrder;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;
use App\Payments\Domain\ProviderSettings;
use App\Payments\Domain\Repository\ProviderConfigurationRepository;
use App\Payments\Domain\Repository\WebhookOperationRepository;
use App\Payments\Domain\WebhookOperation;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use Exception;
use Psr\Clock\ClockInterface;

/**
 * Yoco webhook administration (Java `YocoWebhookManagement`). Each request id is one durable operation: a
 * registration reaches the provider at most once (an uncertain outcome is never retried automatically), its signing
 * secret is kept encrypted until it is saved as a new revision, and a removal is always checked against the provider's
 * current list and refused while payments rely on the callback. Provider calls run outside any transaction; every
 * transaction locks the Yoco settings head first.
 */
final readonly class YocoWebhookManagement
{
    public const string CALLBACK = '/api/payments/webhooks/yoco';

    public function __construct(
        private ProviderConfigurationRepository $configurations,
        private WebhookOperationRepository $operations,
        private PaymentCredentialVault $vault,
        private YocoWebhookReader $reader,
        private YocoWebhookWriter $writer,
        private SaveProviderSettingsHandler $save,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private PaymentAccess $access,
    ) {
    }

    /** Closes an uncertain, ready or (after five minutes) interrupted operation; runs in the caller's transaction. */
    public function close(string $actor, string $request, int $expected, string $reason): void
    {
        $this->access->configure($actor);
        $note = PaymentOrder::text($reason, 500, 'Provider review evidence');
        $head = $this->configurations->lock('yoco');
        $this->access->configure($actor);
        $head->expected($expected);
        $operation = $this->operations->find($request) ?? throw PaymentException::missing();
        $now = $this->clock->now();
        $interrupted = 'STARTED' === $operation->status && $operation->createdAt <= $now->modify('-300 seconds');
        if (!\in_array($operation->status, ['UNKNOWN', 'READY'], true) && !$interrupted) {
            throw PaymentException::conflict('An in-progress operation can be closed after five minutes. Review provider state before closing.');
        }
        $current = $this->configuration($operation->mode);
        $this->protectReason($note, $current);
        $this->protectReason($note, $this->configurations->revision($operation->revisionId) ?? throw PaymentException::missing());
        if (null !== $operation->encryptedSecret) {
            $secret = $this->vault->decrypt($operation->encryptedSecret)->webhookSecret;
            if (\strlen($secret) >= 4 && str_contains($note, $secret)) {
                throw PaymentException::invalid('Keep credentials out of the change reason.');
            }
        }
        $this->configurations->check(new ConfigurationCheck(
            $this->ids->uuid(),
            $current->id,
            false,
            'Webhook operation closed after manual review. Test the connection again.',
            $actor,
            $now
        ));
        $this->configurations->save($head->next($head->mode, false, false, $head->managed));
        $this->operations->finish($request, 'CLOSED', $operation->webhookId, $operation->encryptedSecret);
        $this->configurations->audit('yoco', $operation->mode, $head->version + 1, 'WEBHOOK_REVIEWED', $note, 'webhook operation', $actor, $now);
    }

    public function register(string $actor, string $request, string $mode, int $expected, string $reason): WebhookChange
    {
        [$operation, $created, $note] = $this->begin($actor, $request, $mode, $expected, 'REGISTER', null, $reason);
        if ($operation instanceof WebhookChange) {
            return $operation;
        }
        $original = $this->configurations->revision($operation->revisionId) ?? throw PaymentException::missing();
        if ($created) {
            try {
                $registered = $this->writer->register($this->key($original), 'onechurch-'.$mode.'-'.$request, $original->backendUrl.self::CALLBACK, $mode);
                $secret = $this->vault->encrypt(new ProviderCredentials(null, $registered->secret));
                $this->transactions->execute(fn () => $this->operations->finish($request, 'READY', $registered->id, $secret));
            } catch (Exception) {
                $this->transactions->execute(fn () => $this->operations->finish($request, 'UNKNOWN', null, null));

                return new WebhookChange($request, 'UNKNOWN', 'Registration outcome is uncertain. Refresh the webhook list before creating another subscription; this request will not register twice.');
            }
        }
        $ready = $this->operations->find($request) ?? throw PaymentException::missing();
        if ('READY' !== $ready->status) {
            return new WebhookChange($request, $ready->status, 'Registration is in progress or uncertain. Inspect the webhook list; this request will not register twice.');
        }
        try {
            return $this->transactions->execute(function () use ($actor, $request, $mode, $expected, $note, $original, $ready): WebhookChange {
                $head = $this->configurations->lock('yoco');
                $this->access->configure($actor);
                $head->expected($expected);
                $current = $this->configuration($mode);
                if ($current->backendUrl !== $original->backendUrl || $this->key($current) !== $this->key($original)) {
                    throw PaymentException::conflict('Backend URL or API key changed. The registered secret is retained securely; restore the matching settings before finishing this registration.');
                }
                $secret = $this->vault->decrypt((string) $ready->encryptedSecret)->webhookSecret;
                $this->save->handle(new SaveProviderSettingsCommand(
                    $actor,
                    'yoco',
                    $mode,
                    $expected,
                    false,
                    false,
                    $current->publicUrl,
                    new ProviderCredentials(null, $secret),
                    $note,
                    $current->backendUrl
                ));
                $this->operations->finish($request, 'APPLIED', $ready->webhookId, $ready->encryptedSecret);
                $this->configurations->audit('yoco', $mode, $expected + 1, 'WEBHOOK_REGISTERED', $note, 'webhook registration', $actor, $this->clock->now());

                return new WebhookChange($request, 'APPLIED', 'Webhook registered and signing secret saved securely. Test the saved connection before activation.');
            });
        } catch (PaymentException) {
            return new WebhookChange($request, 'READY', 'Webhook registered; its signing secret is retained securely. Reload settings and finish saving this registration before activation.');
        }
    }

    /** A removal is always checked against current provider evidence, including explicit retries after a timeout. */
    public function remove(string $actor, string $request, string $mode, int $expected, string $reason, ?string $target): WebhookChange
    {
        [$operation, , $note] = $this->begin($actor, $request, $mode, $expected, 'REMOVE', $target, $reason);
        if ($operation instanceof WebhookChange) {
            return $operation;
        }
        $target = (string) $target;
        $original = $this->configurations->revision($operation->revisionId) ?? throw PaymentException::missing();
        $uncertain = 'Removal could not be confirmed. Refresh the list and retry the same request.';
        try {
            $found = array_find($this->reader->list($this->key($original)), static fn (YocoWebhook $v): bool => $v->id === $target && $v->mode === $mode);
            if (null !== $found) {
                $backend = str_ends_with($found->url, self::CALLBACK) ? substr($found->url, 0, -\strlen(self::CALLBACK)) : $found->url;
                $this->transactions->execute(function () use ($actor, $mode, $backend): void {
                    $head = $this->configurations->lock('yoco');
                    $this->access->configure($actor);
                    if ($this->operations->referenced($mode, $backend)) {
                        throw PaymentException::conflict('This callback is referenced by existing payments. Retain it for payment and refund notifications.');
                    }
                    if ($head->enabled && $head->mode === $mode && $this->configuration($mode)->backendUrl === $backend) {
                        throw PaymentException::conflict('Disable new checkouts and save before removing the current callback.');
                    }
                });
                $this->writer->remove($this->key($original), $target);
            }

            return $this->transactions->execute(function () use ($actor, $request, $mode, $note, $target): WebhookChange {
                $head = $this->configurations->lock('yoco');
                $this->access->configure($actor);
                $now = $this->clock->now();
                $current = $this->configuration($mode);
                $this->configurations->check(new ConfigurationCheck(
                    $this->ids->uuid(),
                    $current->id,
                    false,
                    'Webhook list changed. Test the saved connection again before activation.',
                    $actor,
                    $now
                ));
                if ($head->mode === $mode) {
                    $this->configurations->save($head->next($mode, false, false, true));
                }
                $this->operations->finish($request, 'APPLIED', $target, null);
                $this->configurations->audit('yoco', $mode, $head->version, 'WEBHOOK_REMOVED', $note, 'webhook registration', $actor, $now);

                return new WebhookChange($request, 'APPLIED', 'Webhook removed. Existing provider payments were not changed.');
            });
        } catch (PaymentException $blocked) {
            $this->transactions->execute(fn () => $this->operations->finish($request, 'UNKNOWN', $target, null));
            if ($blocked->isConflict()) {
                $this->transactions->execute(fn () => $this->operations->finish($request, 'CLOSED', $target, null));
                throw $blocked;
            }

            return new WebhookChange($request, 'UNKNOWN', $uncertain);
        } catch (Exception) {
            $this->transactions->execute(fn () => $this->operations->finish($request, 'UNKNOWN', $target, null));

            return new WebhookChange($request, 'UNKNOWN', $uncertain);
        }
    }

    /**
     * Records the operation once per request id (an identical retry returns the stored one).
     *
     * @return array{0: WebhookOperation|WebhookChange, 1: bool, 2: string}
     */
    private function begin(string $actor, string $request, string $mode, int $expected, string $kind, ?string $target, string $reason): array
    {
        $this->access->configure($actor);
        ProviderSettings::validate('yoco', $mode);
        $note = PaymentOrder::text($reason, 500, 'Webhook change reason');
        if ('REMOVE' === $kind && (null === $target || 1 !== preg_match('/\Asub_[A-Za-z0-9_-]{1,150}\z/', $target))) {
            throw PaymentException::invalid('Choose a valid Yoco subscription.');
        }
        $hash = PaymentHash::of($actor, $mode, $kind, $target);
        [$operation, $created] = $this->transactions->execute(function () use ($actor, $request, $mode, $expected, $kind, $target, $note, $hash): array {
            $head = $this->configurations->lock('yoco');
            $this->access->configure($actor);
            $head->expected($expected);
            $configuration = $this->configuration($mode);
            $this->protectReason($note, $configuration);
            $prior = $this->operations->find($request);
            if (null !== $prior) {
                if ($prior->requestHash !== $hash) {
                    throw PaymentException::conflict('Webhook request identifier already used for different details.');
                }
                $this->protectReason($note, $this->configurations->revision($prior->revisionId) ?? throw PaymentException::missing());

                return [$prior, false];
            }
            if ('REGISTER' === $kind && $head->enabled) {
                throw PaymentException::conflict('Disable new Yoco checkouts and save before registering a webhook.');
            }
            $fresh = new WebhookOperation(
                $request,
                $configuration->id,
                $mode,
                $kind,
                $target,
                $configuration->backendUrl,
                $hash,
                $actor,
                'STARTED',
                null,
                null,
                $this->clock->now()
            );
            $this->operations->insert($fresh);

            return [$fresh, true];
        });
        if ('CLOSED' === $operation->status) {
            throw PaymentException::conflict('This operation was closed. Use a new request after reviewing the provider list.');
        }
        if ('APPLIED' === $operation->status) {
            return [new WebhookChange($request, 'APPLIED', 'Webhook change already completed.'), false, $note];
        }

        return [$operation, $created, $note];
    }

    private function configuration(string $mode): ProviderConfiguration
    {
        ProviderSettings::validate('yoco', $mode);
        $configuration = $this->configurations->latest('yoco', $mode)
            ?? throw PaymentException::conflict('Save the backend URL and Yoco secret API key first.');
        $this->key($configuration);

        return $configuration;
    }

    private function key(ProviderConfiguration $configuration): string
    {
        $key = $this->vault->decrypt($configuration->encryptedCredentials)->yocoKey;
        if (!str_starts_with($key, 'sk_'.$configuration->mode.'_')) {
            throw PaymentException::conflict('Save a Yoco secret API key for this environment first.');
        }

        return $key;
    }

    private function protectReason(string $note, ProviderConfiguration $configuration): void
    {
        $credentials = $this->vault->decrypt($configuration->encryptedCredentials);
        foreach ([$credentials->yocoKey, $credentials->webhookSecret] as $secret) {
            if (\strlen($secret) >= 4 && str_contains($note, $secret)) {
                throw PaymentException::invalid('Keep credentials out of the change reason.');
            }
        }
    }
}
