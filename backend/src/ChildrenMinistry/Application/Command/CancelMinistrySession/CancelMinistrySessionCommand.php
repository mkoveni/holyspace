<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CancelMinistrySession;

use App\Shared\Application\CQRS\Command;

final readonly class CancelMinistrySessionCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
