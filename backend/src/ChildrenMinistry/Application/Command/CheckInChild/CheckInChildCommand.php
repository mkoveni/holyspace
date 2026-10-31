<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CheckInChild;

use App\Shared\Application\CQRS\Command;

final readonly class CheckInChildCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
