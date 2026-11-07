<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddAddress;

use App\Shared\Application\Command;

/** @implements Command<string> */
final readonly class AddAddressCommand implements Command
{
    public function __construct(
        public string $actorId,
        public string $personId,
        public int $expectedVersion,
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $region,
        public ?string $postalCode,
        public string $countryCode,
    ) {
    }
}
