<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InvoiceSettingsRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 10)] public string $effectiveFrom,
        #[Assert\NotBlank] #[Assert\Length(max: 150)] public string $legalName,
        #[Assert\Length(max: 300)] public string $address,
        #[Assert\Length(max: 40)] public string $registrationNumber,
        #[Assert\Length(max: 40)] public string $npoNumber,
        #[Assert\Length(max: 20)] public string $vatNumber,
        public bool $vatRegistered,
        #[Assert\Range(min: 0, max: 5000)] public int $vatRateBp,
    ) {
    }
}
