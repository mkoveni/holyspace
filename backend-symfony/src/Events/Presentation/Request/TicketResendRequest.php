<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TicketResendRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 254)] public ?string $email = null,
        #[Assert\Length(max: 2048)] public ?string $humanToken = null,
        #[Assert\Length(max: 200)] public ?string $website = null,
    ) {
    }
}
