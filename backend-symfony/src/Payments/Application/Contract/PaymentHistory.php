<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use DateTimeImmutable;

final readonly class PaymentHistory
{
    public function __construct(public string $action, public string $note, public ?string $actorId, public DateTimeImmutable $occurredAt)
    {
    }
}
