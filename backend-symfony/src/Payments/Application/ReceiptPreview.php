<?php

declare(strict_types=1);

namespace App\Payments\Application;

/** A rendered donor confirmation and the hash its approval must quote (Java `PaymentReceiptPreview.Preview`). */
final readonly class ReceiptPreview
{
    public function __construct(
        public string $hash,
        public string $recipient,
        public string $subject,
        public string $html,
        public int $templateVersion,
    ) {
    }

    /** @return array{redacted: true} */
    public function __debugInfo(): array
    {
        return ['redacted' => true];
    }
}
