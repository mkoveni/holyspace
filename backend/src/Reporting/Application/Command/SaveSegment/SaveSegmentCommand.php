<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\SaveSegment;

use App\Shared\Application\CQRS\Command;

final readonly class SaveSegmentCommand implements Command
{
    public function __construct(
        public string $name,
        public array $filters,
        public ?string $id = null,
        public ?int $version = null,
    ) {
    }
}
