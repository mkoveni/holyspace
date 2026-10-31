<?php

declare(strict_types=1);

namespace App\Giving\Application\Command\RecordGift;

use App\Shared\Application\CQRS\Command;

final readonly class RecordGiftCommand implements Command
{
    public function __construct(public array $data)
    {
    }
}
