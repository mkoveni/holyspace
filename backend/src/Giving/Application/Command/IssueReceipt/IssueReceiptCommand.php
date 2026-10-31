<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\IssueReceipt;

use App\Shared\Application\CQRS\Command;

final readonly class IssueReceiptCommand implements Command
{
    public function __construct(public string $giftId)
    {
    }
}
