<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

/** A rendered notice for one member and one current address, with the hash an approval must present. */
final readonly class ReviewedNotice
{
    public function __construct(
        public string $hash,
        public string $recipient,
        public string $subject,
        public string $html,
        public int $templateVersion,
    ) {
    }

    /** The recipient and content stay out of logs (Java `toString`). */
    public function __debugInfo(): array
    {
        return ['hash' => $this->hash];
    }
}
