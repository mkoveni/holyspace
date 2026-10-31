<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CheckOutChild;

use App\Shared\Application\CQRS\Command;

final readonly class CheckOutChildCommand implements Command
{
    public function __construct(public string $id, public array $data)
    {
    }
}
