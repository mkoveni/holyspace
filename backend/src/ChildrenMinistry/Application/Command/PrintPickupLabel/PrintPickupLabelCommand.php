<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\PrintPickupLabel;

use App\Shared\Application\CQRS\Command;

final readonly class PrintPickupLabelCommand implements Command
{
    public function __construct(
        public string $id,
        public int $expectedVersion,
        public string $replacementReason,
    ) {
    }
}
