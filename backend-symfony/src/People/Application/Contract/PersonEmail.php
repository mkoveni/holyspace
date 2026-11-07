<?php

declare(strict_types=1);

namespace App\People\Application\Contract;

final readonly class PersonEmail
{
    public function __construct(public string $id, public string $value)
    {
    }
}
