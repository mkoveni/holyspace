<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\CollectChild;

use App\Shared\Application\CQRS\Command;

final readonly class CollectChildCommand implements Command
{
    public function __construct(public string $id, public array $data)
    {
    }
}
