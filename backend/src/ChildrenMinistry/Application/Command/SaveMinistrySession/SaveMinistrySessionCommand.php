<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\SaveMinistrySession;

use App\Shared\Application\CQRS\Command;

final readonly class SaveMinistrySessionCommand implements Command
{
    public function __construct(public array $data, public ?string $id = null)
    {
    }
}
