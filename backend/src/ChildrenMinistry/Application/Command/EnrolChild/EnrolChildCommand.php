<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\EnrolChild;

use App\Shared\Application\CQRS\Command;

final readonly class EnrolChildCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
