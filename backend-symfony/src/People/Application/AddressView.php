<?php

declare(strict_types=1);

namespace App\People\Application;

use DateTimeImmutable;

/** Read model of an address, current or ended. */
final readonly class AddressView
{
    public function __construct(
        public string $id,
        public string $personId,
        public string $line1,
        public string $line2,
        public string $city,
        public string $region,
        public string $postalCode,
        public string $countryCode,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $endedAt,
    ) {
    }
}
