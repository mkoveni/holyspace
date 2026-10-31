<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CreateProgramme;

use App\Shared\Application\CQRS\Command;

final readonly class CreateProgrammeCommand implements Command
{
    public function __construct(public string $name)
    {
    }
}
