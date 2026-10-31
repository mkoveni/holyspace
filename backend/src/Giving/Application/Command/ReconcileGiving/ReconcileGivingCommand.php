<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReconcileGiving;

use App\Shared\Application\CQRS\Command;

final readonly class ReconcileGivingCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
