<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

final readonly class Title
{
    public function __construct(public string $code, public string $label)
    {
    }
}
