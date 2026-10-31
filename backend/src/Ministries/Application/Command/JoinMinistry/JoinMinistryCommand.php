<?php

declare(strict_types=1);

namespace App\Ministries\Application\Command\JoinMinistry;

use App\Shared\Application\CQRS\Command;

final readonly class JoinMinistryCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
