<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreatePledge;

use App\Shared\Application\CQRS\Command;

final readonly class CreatePledgeCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
