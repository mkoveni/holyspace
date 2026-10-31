<?php

declare(strict_types=1);

namespace App\Events\Application\Command;

use App\Shared\Application\CQRS\Command;

final readonly class UpdateEvent implements Command
{
    public function __construct(
        public string $id,
        public string $title,
        public string $type,
        public ?string $description,
    ) {
    }
}
