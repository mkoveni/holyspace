<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateFamily;

use App\Shared\Application\CQRS\Command;

final readonly class CreateFamilyCommand implements Command
{
    public function __construct(public string $name)
    {
    }
}
