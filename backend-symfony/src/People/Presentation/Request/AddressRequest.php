<?php

declare(strict_types=1);

namespace App\People\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddressRequest
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $expectedVersion,
        public string $line1 = '',
        public ?string $line2 = null,
        public string $city = '',
        public ?string $region = null,
        public ?string $postalCode = null,
        public string $countryCode = '',
    ) {
    }
}
