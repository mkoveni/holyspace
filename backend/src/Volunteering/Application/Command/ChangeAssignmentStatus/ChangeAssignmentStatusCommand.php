<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\ChangeAssignmentStatus;

use App\Shared\Application\CQRS\Command;

final readonly class ChangeAssignmentStatusCommand implements Command
{
    public function __construct(public string $id, public string $status)
    {
    }
}
