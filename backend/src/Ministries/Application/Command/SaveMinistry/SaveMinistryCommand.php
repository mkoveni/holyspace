<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\SaveMinistry;

use App\Shared\Application\CQRS\Command;

final readonly class SaveMinistryCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
