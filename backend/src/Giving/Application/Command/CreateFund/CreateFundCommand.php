<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\CreateFund;

use App\Shared\Application\CQRS\Command;

final readonly class CreateFundCommand implements Command
{
    public function __construct(public string $name)
    {
    }
}
