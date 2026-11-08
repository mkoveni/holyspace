<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

final readonly class StoredRequest
{
    public function __construct(public string $hash, public string $targetId)
    {
    }
}
