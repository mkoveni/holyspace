<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\PublishForm;

use App\Shared\Application\CQRS\Command;

final readonly class PublishFormCommand implements Command
{
    public function __construct(
        public string $id,
        public bool $published,
        public int $version,
    ) {
    }
}
