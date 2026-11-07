<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

final readonly class ReadinessCheck
{
    public function __construct(public string $key, public string $title, public string $status, public string $detail)
    {
    }
}
