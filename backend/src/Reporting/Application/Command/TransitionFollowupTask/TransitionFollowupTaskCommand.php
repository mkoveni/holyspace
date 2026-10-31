<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\TransitionFollowupTask;

use App\Shared\Application\CQRS\Command;

final readonly class TransitionFollowupTaskCommand implements Command
{
    public function __construct(
        public string $id,
        public int $version,
        public string $status,
        public string $note,
    ) {
    }
}
