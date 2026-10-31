<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\ArchiveMinistry;

use App\Shared\Application\CQRS\Command;

final readonly class ArchiveMinistryCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
