<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\ReverseGift;

use App\Shared\Application\CQRS\Command;

final readonly class ReverseGiftCommand implements Command
{
    public function __construct(
        public string $id,
        public string $date,
        public string $reason,
    ) {
    }
}
