<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\DeliverNextMessage;

use App\Shared\Application\CQRS\Command;

final readonly class DeliverNextMessageCommand implements Command
{
    public function __construct()
    {
    }
}
