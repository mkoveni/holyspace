<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\MaintainPayments;

use App\Shared\Application\CQRS\Command;

final readonly class MaintainPaymentsCommand implements Command
{
    public function __construct()
    {
    }
}
