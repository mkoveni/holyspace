<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** The client names ticket types and quantities, never an amount. */
final readonly class TicketOrderRequest
{
    /** @param array<string, mixed>|null $quantities ticket type id => number of tickets */
    public function __construct(
        #[Assert\NotBlank] #[Assert\Uuid] public ?string $requestId = null,
        #[Assert\NotNull] #[Assert\Count(min: 1, max: 10)] public ?array $quantities = null,
        #[Assert\NotBlank] #[Assert\Length(max: 100)] public ?string $name = null,
        #[Assert\NotBlank] #[Assert\Length(max: 254)] public ?string $email = null,
        #[Assert\Length(max: 2048)] public ?string $humanToken = null,
        /** Honeypot: people never see this field. */
        #[Assert\Length(max: 200)] public ?string $website = null,
    ) {
    }
}
