<?php

declare(strict_types=1);

namespace App\Payments\Application\Contract;

use App\Shared\Domain\CalendarDate;
use SensitiveParameter;

/** An authenticated provider event (Java `PaymentGateway.Notification`); type is PAID, FAILED, REFUNDED or REFUND_FAILED. */
final readonly class ProviderNotification
{
    public function __construct(
        public string $provider,
        public string $mode,
        public string $eventId,
        public string $type,
        public string $reference,
        public ?string $checkoutId,
        public ?string $orderId,
        public int $amountMinor,
        public string $currency,
        #[SensitiveParameter] public ?string $token,
        public ?CalendarDate $date,
    ) {
    }

    /** @return array{provider: string, eventId: string} */
    public function __debugInfo(): array
    {
        return ['provider' => $this->provider, 'eventId' => $this->eventId];
    }
}
