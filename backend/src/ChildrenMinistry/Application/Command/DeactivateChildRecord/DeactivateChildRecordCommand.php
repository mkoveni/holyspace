<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\DeactivateChildRecord;

use App\Shared\Application\CQRS\Command;

final readonly class DeactivateChildRecordCommand implements Command
{
    public function __construct(public string $kind, public string $id)
    {
    }
}
