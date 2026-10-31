<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\ResolveDelivery;

use App\Shared\Application\CQRS\Command;

final readonly class ResolveDeliveryCommand implements Command
{
    public function __construct(
        public string $id,
        public string $status,
        public string $note,
    ) {
    }
}
