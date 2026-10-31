<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\SaveMinistryClass;

use App\Shared\Application\CQRS\Command;

final readonly class SaveMinistryClassCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
