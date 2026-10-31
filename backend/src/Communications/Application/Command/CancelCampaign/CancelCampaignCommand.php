<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\CancelCampaign;

use App\Shared\Application\CQRS\Command;

final readonly class CancelCampaignCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
