<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

final readonly class TransportConfiguration
{
    public function __construct(public bool $enabled, public bool $capture, public string $mode)
    {
    }
}
