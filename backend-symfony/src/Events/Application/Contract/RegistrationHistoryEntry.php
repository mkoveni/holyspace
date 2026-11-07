<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

use DateTimeImmutable;

final readonly class RegistrationHistoryEntry
{
    public function __construct(public string $id, public string $action, public string $reason, public DateTimeImmutable $occurredAt)
    {
    }
}
