<?php

declare(strict_types=1);

namespace App\Payments\Application\Command\ProcessPaymentNotification;

use App\Shared\Application\CQRS\Command;

final readonly class ProcessPaymentNotificationCommand implements Command
{
    public function __construct(
        public string $provider,
        public string $body,
        public array $headers,
        public string $ip,
    ) {
    }
}
