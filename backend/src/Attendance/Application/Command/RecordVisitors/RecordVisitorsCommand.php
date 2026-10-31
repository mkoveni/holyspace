<?php

declare(strict_types=1);

namespace App\Attendance\Application\Command\RecordVisitors;

use App\Shared\Application\CQRS\Command;

final readonly class RecordVisitorsCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
