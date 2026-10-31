<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\ChangeOwnPassword;

use App\Shared\Application\CQRS\Command;

final readonly class ChangeOwnPasswordCommand implements Command
{
    public function __construct(public string $current, public string $replacement)
    {
    }
}
