<?php

declare(strict_types=1);

namespace App\Giving\Presentation\Http\DTO;

final readonly class GivingFilter
{
    public function __construct(public ?string $from = null, public ?string $to = null, public ?string $fundId = null)
    {
    }
}
