<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

final readonly class CommunicationPage
{
    /** @param list<CommunicationSummary> $communications */
    public function __construct(public array $communications, public int $total, public int $overdue)
    {
    }
}
