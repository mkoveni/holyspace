<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\ReassignFollowupTask;

use App\Shared\Application\CQRS\Command;

final readonly class ReassignFollowupTaskCommand implements Command
{
    public function __construct(
        public string $id,
        public int $version,
        public string $assigneeId,
        public string $dueOn,
        public string $reason,
    ) {
    }
}
