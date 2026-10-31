<?php

declare(strict_types=1);

namespace App\People\Application\Command\ChangeMembershipStatus;

use App\Shared\Application\CQRS\Command;

final readonly class ChangeMembershipStatusCommand implements Command
{
    public function __construct(public string $personId, public string $status)
    {
    }
}
