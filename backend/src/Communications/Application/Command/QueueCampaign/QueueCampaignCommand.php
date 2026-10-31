<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\QueueCampaign;

use App\Shared\Application\CQRS\Command;

final readonly class QueueCampaignCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
