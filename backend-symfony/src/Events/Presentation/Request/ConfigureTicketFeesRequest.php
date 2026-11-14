<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfigureTicketFeesRequest
{
    public function __construct(
        #[Assert\PositiveOrZero] public int $expectedVersion,
        #[Assert\PositiveOrZero] public int $feeRatePpm,
        #[Assert\PositiveOrZero] public int $feeFixedMinor,
        #[Assert\PositiveOrZero] public int $providerVatPpm,
        #[Assert\Positive] public int $holdMinutes,
    ) {
    }
}
