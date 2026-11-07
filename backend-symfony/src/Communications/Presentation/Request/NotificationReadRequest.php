<?php

declare(strict_types=1);

namespace App\Communications\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NotificationReadRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?bool $read = null,
    ) {
    }
}
