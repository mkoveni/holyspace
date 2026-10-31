<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Command\PublishServicePlan;

use App\Shared\Application\CQRS\Command;

final readonly class PublishServicePlanCommand implements Command
{
    public function __construct(
        public string $id,
        public int $version,
        public bool $published,
    ) {
    }
}
