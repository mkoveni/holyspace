<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

final readonly class PledgePayload
{
    public function __construct(public string $donorId, public string $fundId, public int $amountMinor, public string $currency, public string $dueOn)
    {
    }
}
