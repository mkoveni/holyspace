<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

final readonly class ProbeResult
{
    public function __construct(public bool $successful, public string $message)
    {
    }
}
