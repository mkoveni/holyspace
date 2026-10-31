<?php

declare(strict_types=1);

namespace App\People\Application\Command\AddFamilyAddress;

use App\Shared\Application\CQRS\Command;

final readonly class AddFamilyAddressCommand implements Command
{
    public function __construct(
        public string $familyId,
        public string $type,
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $province,
        public ?string $postalCode,
        public string $country,
    ) {
    }
}
