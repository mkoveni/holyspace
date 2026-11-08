<?php

declare(strict_types=1);

namespace App\Tests\Payments\Support;

use App\Payments\Application\Contract\ProbeResult;
use App\Payments\Application\Contract\ProviderConnectionProbe;
use App\Payments\Domain\ProviderConfiguration;
use App\Payments\Domain\ProviderCredentials;
use App\Payments\Infrastructure\Gateway\HostedProviderConnectionProbe;
use Closure;
use Doctrine\DBAL\Connection;

/** The real probe (through the scripted HTTP) unless a test scripts `check`. */
final class ScriptedConnectionProbe implements ProviderConnectionProbe
{
    public ?Closure $check = null;
    public int $checks = 0;
    public bool $inTransaction = false;

    public function __construct(private readonly HostedProviderConnectionProbe $real, private readonly Connection $connection)
    {
    }

    public function reset(): void
    {
        $this->check = null;
        $this->checks = 0;
        $this->inTransaction = false;
    }

    public function validateNetworks(string $ipRanges): void
    {
        $this->real->validateNetworks($ipRanges);
    }

    public function check(ProviderConfiguration $configuration, ProviderCredentials $credentials): ProbeResult
    {
        ++$this->checks;
        $this->inTransaction = $this->inTransaction || $this->connection->isTransactionActive();

        return null === $this->check ? $this->real->check($configuration, $credentials) : ($this->check)();
    }
}
