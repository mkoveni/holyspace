<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\Communications\Application\Contract\DeliverySummary;

final readonly class CommunicationRow
{
    public function __construct(public CommunicationSummary $communication, public string $childName, public string $guardianName, public ?DeliverySummary $delivery)
    {
    }
}
