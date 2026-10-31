<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\WithdrawCommunicationConsent;

use App\Shared\Application\CQRS\Command;

final readonly class WithdrawCommunicationConsentCommand implements Command
{
    public function __construct(public string $id)
    {
    }
}
