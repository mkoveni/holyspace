<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailTransport;
use App\Communications\Application\Contract\TransportConfiguration;
use App\Communications\Application\Contract\TransportResult;

/** The mail transport in the test environment: nothing leaves the process, and every send is recorded. Disabled until a test enables it. */
final class RecordingEmailTransport implements EmailTransport
{
    public bool $enabled = false;

    public bool $capture = false;

    /** The status the next sends report: SENT, FAILED or UNKNOWN. */
    public string $outcome = 'SENT';

    /** @var list<DeliverySnapshot> */
    public array $sent = [];

    public function configuration(): TransportConfiguration
    {
        return new TransportConfiguration($this->enabled, $this->capture, 'Test transport');
    }

    public function send(DeliverySnapshot $delivery): TransportResult
    {
        $this->sent[] = $delivery;

        return new TransportResult($this->outcome, 'SENT' === $this->outcome ? '' : 'TEST_FAILURE', $this->capture);
    }

    public function reset(): void
    {
        $this->enabled = false;
        $this->capture = false;
        $this->outcome = 'SENT';
        $this->sent = [];
    }
}
