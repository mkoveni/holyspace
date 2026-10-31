<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ConfigureOnlineFunds;

use App\Shared\Application\CQRS\Command;

final readonly class ConfigureOnlineFundsCommand implements Command
{
    public function __construct(public array $fundIds, public int $version)
    {
    }
}
