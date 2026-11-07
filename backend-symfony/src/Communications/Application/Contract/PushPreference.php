<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

final readonly class PushPreference
{
    public function __construct(public string $topic, public bool $enabled, public int $version)
    {
    }
}
