<?php

declare(strict_types=1);

namespace App\Tests\Payments\Support;

use App\Payments\Infrastructure\Gateway\ProviderHttp;
use Closure;
use Doctrine\DBAL\Connection;
use LogicException;

/** No network in tests: answers through a scripted handler and records any call made inside a transaction. */
final class ScriptedProviderHttp implements ProviderHttp
{
    public ?Closure $handler = null;
    public bool $inTransaction = false;
    /** @var list<array{string, string}> */
    public array $calls = [];

    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    public function reset(): void
    {
        $this->handler = null;
        $this->inTransaction = false;
        $this->calls = [];
    }

    public function send(string $method, string $url, array $headers, string $body): string
    {
        $this->calls[] = [$method, $url];
        $this->inTransaction = $this->inTransaction || true === $this->connection?->isTransactionActive();
        if (null === $this->handler) {
            throw new LogicException('Unexpected provider call '.$method.' '.$url);
        }

        return (string) ($this->handler)($method, $url, $headers, $body);
    }
}
