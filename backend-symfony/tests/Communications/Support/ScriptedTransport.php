<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailTransport;
use App\Communications\Application\Contract\TransportConfiguration;
use App\Communications\Application\Contract\TransportResult;

/** A transport that answers with a chosen result and remembers what it was asked to send. */
final class ScriptedTransport implements EmailTransport
{
    public bool $enabled = true;
    public bool $capture = false;
    public TransportResult $result;

    /** @var list<string> recipient|subject|message id */
    public array $sent = [];

    public function __construct()
    {
        $this->result = new TransportResult('SENT', '', false);
    }

    public function configuration(): TransportConfiguration
    {
        return new TransportConfiguration($this->enabled, $this->capture, 'SMTP');
    }

    public function send(DeliverySnapshot $delivery): TransportResult
    {
        $this->sent[] = $delivery->recipient.'|'.$delivery->subject.'|'.$delivery->messageId;

        return $this->result;
    }
}
