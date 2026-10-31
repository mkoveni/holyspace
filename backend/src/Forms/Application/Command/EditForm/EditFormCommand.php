<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\EditForm;

use App\Shared\Application\CQRS\Command;

final readonly class EditFormCommand implements Command
{
    public function __construct(
        public string $id,
        public array $data,
        public int $version,
    ) {
    }
}
