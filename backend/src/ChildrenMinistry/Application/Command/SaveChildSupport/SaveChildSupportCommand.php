<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\SaveChildSupport;

use App\Shared\Application\CQRS\Command;

final readonly class SaveChildSupportCommand implements Command
{
    public function __construct(
        public string $sessionId,
        public string $childId,
        public string $text,
        public int $expectedVersion,
    ) {
    }
}
