<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\CheckProviderConnection;

use App\Payments\Application\Contract\PaymentCredentialVault;
use App\Payments\Application\Contract\ProbeResult;
use App\Payments\Application\Contract\ProviderConnectionProbe;
use App\Payments\Application\PaymentAccess;
use App\Payments\Domain\ConfigurationCheck;
use App\Payments\Domain\PaymentException;
use App\Payments\Domain\ProviderSettings;
use App\Payments\Domain\Repository\ProviderConfigurationRepository;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\JavaString;
use Exception;
use Psr\Clock\ClockInterface;

/**
 * Runs a non-charging connection check on the latest saved revision and records its evidence. The probe runs outside
 * any transaction; the result is recorded only if the settings and revision did not change meanwhile. Provider error
 * text is never stored (it may echo credentials). Provider boundary: not on the command bus.
 */
final readonly class CheckProviderConnectionHandler
{
    public function __construct(
        private PaymentAccess $access,
        private ProviderConfigurationRepository $repository,
        private PaymentCredentialVault $vault,
        private ProviderConnectionProbe $probe,
        private UnitOfWork $transactions,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    public function handle(CheckProviderConnectionCommand $c): ConfigurationCheck
    {
        $this->access->configure($c->actorId);
        ProviderSettings::validate($c->provider, $c->mode);
        $configuration = $this->transactions->execute(function () use ($c) {
            $current = $this->repository->lock($c->provider);
            $this->access->configure($c->actorId);
            $current->expected($c->expectedVersion);

            return $this->repository->latest($c->provider, $c->mode) ?? throw PaymentException::conflict('Save the connection details first.');
        });
        $credentials = $this->vault->decrypt($configuration->encryptedCredentials);
        $issue = $credentials->validationIssue($c->provider, $c->mode);
        if (null !== $issue) {
            $result = new ProbeResult(false, $issue);
        } elseif ('payfast' === $c->provider && JavaString::isBlank($configuration->ipRanges)) {
            $result = new ProbeResult(false, 'Enter the trusted Payfast notification networks in Advanced callback protection, then save and retry.');
        } else {
            try {
                $result = $this->probe->check($configuration, $credentials);
            } catch (Exception) {
                $result = new ProbeResult(false, 'Provider connection could not be confirmed. Check the credentials, environment and provider access, then retry.');
            }
        }
        $check = new ConfigurationCheck($this->ids->uuid(), $configuration->id, $result->successful, $result->message, $c->actorId, $this->clock->now());

        return $this->transactions->execute(function () use ($c, $configuration, $check): ConfigurationCheck {
            $current = $this->repository->lock($c->provider);
            $this->access->configure($c->actorId);
            $current->expected($c->expectedVersion);
            if ($this->repository->latest($c->provider, $c->mode)?->id !== $configuration->id) {
                throw PaymentException::conflict('Connection details changed during the check. Test the current saved settings.');
            }
            $this->repository->check($check);
            $this->repository->audit(
                $c->provider,
                $c->mode,
                $current->version,
                'CONNECTION_CHECKED',
                $check->message,
                $check->successful ? 'successful' : 'failed',
                $c->actorId,
                $this->clock->now()
            );

            return $check;
        });
    }
}
