<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\CreateFollowupTask;

use App\Shared\Application\CQRS\Command;

final readonly class CreateFollowupTaskCommand implements Command
{
    public function __construct(
        public string $personId,
        public string $assigneeId,
        public string $title,
        public string $dueOn,
        public ?string $segmentId = null,
    ) {
    }
}
