<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureTicketFees;

use App\Shared\Application\Command;

/** @implements Command<null> */
final readonly class ConfigureTicketFeesCommand implements Command
{
    public function __construct(public string $actorId, public int $expectedVersion, public int $feeRatePpm, public int $feeFixedMinor, public int $providerVatPpm, public int $holdMinutes)
    {
    }
}
