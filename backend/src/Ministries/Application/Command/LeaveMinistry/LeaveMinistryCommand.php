<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\LeaveMinistry;

use App\Shared\Application\CQRS\Command;

final readonly class LeaveMinistryCommand implements Command
{
    public function __construct(public string $id, public string $date)
    {
    }
}
