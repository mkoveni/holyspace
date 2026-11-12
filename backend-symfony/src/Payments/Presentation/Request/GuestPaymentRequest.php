<?php

declare(strict_types=1);

namespace App\Payments\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** A public (guest) giving checkout. The email is compulsory; no person is ever part of the request. */
final readonly class GuestPaymentRequest
{
    public function __construct(
        #[Assert\NotNull] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotNull] #[Assert\Uuid] public ?string $fundId = null,
        #[Assert\NotNull] #[Assert\Type('int')] #[Assert\Range(min: 500, max: 100_000_000)] public mixed $amountMinor = null,
        #[Assert\NotBlank] public ?string $provider = null,
        #[Assert\NotBlank] #[Assert\Length(max: 254)] #[Assert\Email] public ?string $email = null,
        #[Assert\Length(max: 100)] public ?string $name = null,
        #[Assert\Length(max: 2048)] public ?string $humanToken = null,
        /** Honeypot: people never see this field. */
        #[Assert\Length(max: 200)] public ?string $website = null,
    ) {
    }
}
