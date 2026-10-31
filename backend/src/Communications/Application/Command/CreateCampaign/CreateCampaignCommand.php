<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\CreateCampaign;

use App\Shared\Application\CQRS\Command;

final readonly class CreateCampaignCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
