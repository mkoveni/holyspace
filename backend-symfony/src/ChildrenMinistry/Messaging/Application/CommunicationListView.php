<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

final readonly class CommunicationListView
{
    /** @param list<CommunicationRow> $communications */
    public function __construct(public array $communications, public int $total, public int $overdue)
    {
    }
}
