<?php

declare(strict_types=1);

namespace App\Events\Presentation\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TicketTokenRequest
{
    public function __construct(#[Assert\NotBlank] #[Assert\Length(max: 200)] public ?string $token = null)
    {
    }
}
