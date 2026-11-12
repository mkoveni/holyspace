<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class VisitorChildRow
{
    public function __construct(public string $id, public string $firstName, public DateTimeImmutable $birthDate, public string $handoverStatus)
    {
    }
}
