<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

/** The communication an actor already prepared with a request id, for idempotent retries. */
final readonly class CommunicationReceipt
{
    public function __construct(public string $id, public string $hash)
    {
    }
}
