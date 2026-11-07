<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Communications\Application\Contract\PushTransport;
use App\Communications\Application\Contract\PushTransportResult;
use Doctrine\DBAL\Connection;

/** Push provider double: answers with scripted results and records whether it was ever called inside a transaction. */
final class ScriptedPushTransport implements PushTransport
{
    public int $calls = 0;

    public bool $inTransaction = false;

    public PushTransportResult $send;

    public PushTransportResult $receipt;

    /** @var list<string> */
    public array $tokens = [];

    public function __construct(private readonly Connection $connection)
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->calls = 0;
        $this->inTransaction = false;
        $this->tokens = [];
        $this->send = new PushTransportResult('ACCEPTED', 'ticket-1', '');
        $this->receipt = new PushTransportResult('PROVIDER_ACCEPTED', 'ticket-1', '');
    }

    public function send(string $token, string $notificationId): PushTransportResult
    {
        $this->record();
        $this->tokens[] = $token;

        return $this->send;
    }

    public function receipt(string $ticketId): PushTransportResult
    {
        $this->record();

        return $this->receipt;
    }

    private function record(): void
    {
        ++$this->calls;
        $this->inTransaction = $this->inTransaction || $this->connection->isTransactionActive();
    }
}
