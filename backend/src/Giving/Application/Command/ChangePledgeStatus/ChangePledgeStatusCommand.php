<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ChangePledgeStatus;

use App\Shared\Application\CQRS\Command;

final readonly class ChangePledgeStatusCommand implements Command
{
    public function __construct(public string $id, public string $status)
    {
    }
}
