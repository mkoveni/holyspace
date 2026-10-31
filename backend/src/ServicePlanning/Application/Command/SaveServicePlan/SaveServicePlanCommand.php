<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Command\SaveServicePlan;

use App\Shared\Application\CQRS\Command;

final readonly class SaveServicePlanCommand implements Command
{
    public function __construct(
        public string $occurrenceId,
        public string $title,
        public array $items,
        public ?string $id = null,
        public ?int $version = null,
    ) {
    }
}
