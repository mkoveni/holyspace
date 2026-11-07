<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Incidents\Application;

/** The report a staff member already filed with a request id, for idempotent retries. */
final readonly class IncidentReceipt
{
    public function __construct(public string $id, public string $requestHash)
    {
    }
}
