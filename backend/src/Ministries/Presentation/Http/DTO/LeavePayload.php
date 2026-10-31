<?php

declare(strict_types=1);

namespace App\Ministries\Presentation\Http\DTO;

final readonly class LeavePayload
{
    public function __construct(public string $leftOn)
    {
    }
}
